<?php

namespace App\RecurringTransaction\Service;

use App\Entity\Account;
use App\Entity\RecurringTransaction;
use App\Entity\Transaction;
use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionType;
use App\RecurringTransaction\Repository\RecurringTransactionRepository;
use App\Transaction\Repository\TransactionRepository;
use DateTimeImmutable;
use Money\Money;

class RecurringTransactionDetector
{
    private const MIN_CONFIDENCE_THRESHOLD = 0.70;
    private const MONTHS_TO_ANALYZE = 36;

    // The window (in months) for checking recent activity
    private const RECENT_MONTHS = 12;

    // Intervals exceeding maxDays * this multiplier are treated as gaps
    private const GAP_THRESHOLD_MULTIPLIER = 3;

    // Max intervals since last occurrence before considering pattern dead
    private const MAX_MISSED_INTERVALS = 2;

    // Weights for confidence calculation
    private const WEIGHT_OCCURRENCE = 0.30;
    private const WEIGHT_INTERVAL = 0.40;
    private const WEIGHT_AMOUNT = 0.30;

    public function __construct(
        private TransactionRepository $transactionRepository,
        private RecurringTransactionRepository $recurringTransactionRepository,
        private MerchantNormalizer $merchantNormalizer,
    ) {
    }

    /**
     * Detect recurring transaction patterns for an account
     *
     * @return RecurringTransaction[] Detected recurring transactions
     */
    public function detect(Account $account, bool $force = false): array
    {
        // If force, delete existing patterns first
        if ($force) {
            $this->recurringTransactionRepository->deleteAllForAccount($account);
        }

        // Get transactions from the past 36 months
        $startDate = (new DateTimeImmutable())->modify('-' . self::MONTHS_TO_ANALYZE . ' months');
        $transactions = $this->getTransactionsForAnalysis($account, $startDate);

        if (count($transactions) < 3) {
            return [];
        }

        // Group transactions by normalized merchant
        $groups = $this->groupByMerchant($transactions);

        // Analyze each group for recurring patterns
        $detectedPatterns = [];
        foreach ($groups as $merchantPattern => $groupedTransactions) {
            $pattern = $this->analyzeGroup($account, $merchantPattern, $groupedTransactions);
            if ($pattern !== null) {
                $detectedPatterns[] = $pattern;
            }
        }

        // Save detected patterns
        if (!empty($detectedPatterns)) {
            $this->recurringTransactionRepository->saveAll($detectedPatterns);
        }

        return $detectedPatterns;
    }

    /**
     * Get transactions for analysis (excluding splits)
     *
     * @return Transaction[]
     */
    private function getTransactionsForAnalysis(Account $account, DateTimeImmutable $startDate): array
    {
        $qb = $this->transactionRepository->createQueryBuilder('t')
            ->where('t.account = :account')
            ->andWhere('t.date >= :startDate')
            ->andWhere('t.parentTransaction IS NULL')  // Exclude split children
            ->setParameter('account', $account)
            ->setParameter('startDate', $startDate)
            ->orderBy('t.date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Group transactions by normalized merchant
     *
     * @param Transaction[] $transactions
     * @return array<string, Transaction[]>
     */
    private function groupByMerchant(array $transactions): array
    {
        $groups = [];

        foreach ($transactions as $transaction) {
            $pattern = $this->merchantNormalizer->normalize($transaction);
            if (empty($pattern)) {
                continue;
            }

            if (!isset($groups[$pattern])) {
                $groups[$pattern] = [];
            }
            $groups[$pattern][] = $transaction;
        }

        return $groups;
    }

    /**
     * Analyze a group of transactions for recurring patterns
     *
     * @param Transaction[] $transactions
     */
    private function analyzeGroup(
        Account $account,
        string $merchantPattern,
        array $transactions
    ): ?RecurringTransaction {
        // Sort by date
        usort($transactions, fn(Transaction $a, Transaction $b) =>
            $a->getDate() <=> $b->getDate()
        );

        // Need at least 2 transactions
        if (count($transactions) < 2) {
            return null;
        }

        // Separate by transaction type (debit/credit)
        $byType = [
            TransactionType::DEBIT->value => [],
            TransactionType::CREDIT->value => [],
        ];

        foreach ($transactions as $t) {
            $type = $t->getTransactionType()->value;
            $byType[$type][] = $t;
        }

        // Analyze each type separately
        foreach ($byType as $type => $typedTransactions) {
            if (count($typedTransactions) < 2) {
                continue;
            }

            $result = $this->detectFrequency($typedTransactions);
            if ($result === null) {
                continue;
            }

            [$frequency, $intervalConsistency] = $result;

            // Check if we have minimum occurrences for this frequency
            if (count($typedTransactions) < $frequency->getMinOccurrences()) {
                continue;
            }

            // Recency check: ensure the pattern is still active in the last RECENT_MONTHS
            $recentCutoff = (new DateTimeImmutable())->modify('-' . self::RECENT_MONTHS . ' months');
            $recentCount = 0;
            foreach ($typedTransactions as $t) {
                $txDate = $t->getDate();
                if ($txDate instanceof \DateTime) {
                    $txDate = DateTimeImmutable::createFromMutable($txDate);
                }
                if ($txDate >= $recentCutoff) {
                    $recentCount++;
                }
            }
            if ($recentCount < $this->getMinRecentOccurrences($frequency)) {
                continue;
            }

            // Check if existing pattern exists
            $existing = $this->recurringTransactionRepository->findByMerchantPattern(
                $account,
                $merchantPattern
            );
            if ($existing !== null) {
                continue;
            }

            // Calculate confidence score
            $confidence = $this->calculateConfidence(
                $typedTransactions,
                $frequency,
                $intervalConsistency
            );

            if ($confidence < self::MIN_CONFIDENCE_THRESHOLD) {
                continue;
            }

            // Get the most recent transaction
            $lastTransaction = end($typedTransactions);
            $lastDate = $lastTransaction->getDate();

            // Check if pattern is still active (not too many missed intervals)
            $daysSinceLastOccurrence = (new DateTimeImmutable())->diff(
                DateTimeImmutable::createFromMutable($lastDate)
            )->days;
            $maxDaysSinceLastOccurrence = $frequency->getAverageDays() * self::MAX_MISSED_INTERVALS;

            if ($daysSinceLastOccurrence > $maxDaysSinceLastOccurrence) {
                // Pattern appears to have ended - skip it
                continue;
            }

            // Use the last transaction's amount (most current, reflects raises/changes)
            $lastAmount = (int) $lastTransaction->getAmount()->getAmount();
            $amountVariance = $this->calculateAmountVariance($typedTransactions, $lastAmount);
            $displayName = $this->merchantNormalizer->extractDisplayName($lastTransaction);

            // Create recurring transaction entity
            $recurring = new RecurringTransaction();
            $recurring->setAccount($account);
            $recurring->setMerchantPattern($merchantPattern);
            $recurring->setDisplayName($displayName);
            $recurring->setPredictedAmount(Money::EUR($lastAmount));
            $recurring->setAmountVariance($amountVariance);
            $recurring->setFrequency($frequency);
            $recurring->setConfidenceScore($confidence);
            $recurring->setLastOccurrence(
                DateTimeImmutable::createFromMutable($lastTransaction->getDate())
            );
            $recurring->setOccurrenceCount(count($typedTransactions));
            $recurring->setIntervalConsistency($intervalConsistency);
            $recurring->setTransactionType(TransactionType::from($type));
            $recurring->setCategory($lastTransaction->getCategory());
            $recurring->updateNextExpected();

            return $recurring;
        }

        return null;
    }

    /**
     * Detect the frequency pattern from transactions
     *
     * @param Transaction[] $transactions
     * @return array{RecurrenceFrequency, float}|null [frequency, intervalConsistency]
     */
    private function detectFrequency(array $transactions): ?array
    {
        if (count($transactions) < 2) {
            return null;
        }

        // Calculate intervals between consecutive transactions
        $intervals = [];
        for ($i = 1; $i < count($transactions); $i++) {
            $prev = $transactions[$i - 1]->getDate();
            $curr = $transactions[$i]->getDate();
            $intervals[] = $prev->diff($curr)->days;
        }

        if (empty($intervals)) {
            return null;
        }

        // Try each frequency pattern and find best match
        $bestMatch = null;
        $bestConsistency = 0;

        foreach (RecurrenceFrequency::cases() as $frequency) {
            $consistency = $this->calculateIntervalConsistency($intervals, $frequency);

            if ($consistency > $bestConsistency && $consistency >= 0.6) {
                $bestMatch = $frequency;
                $bestConsistency = $consistency;
            }
        }

        if ($bestMatch === null) {
            return null;
        }

        return [$bestMatch, $bestConsistency];
    }

    /**
     * Calculate how consistent the intervals are with a given frequency (gap-aware)
     */
    private function calculateIntervalConsistency(array $intervals, RecurrenceFrequency $frequency): float
    {
        $minDays = $frequency->getMinDays();
        $maxDays = $frequency->getMaxDays();
        $gapThreshold = $maxDays * self::GAP_THRESHOLD_MULTIPLIER;

        $matchingIntervals = 0;
        $nonGapIntervals = 0;
        $gapCount = 0;

        foreach ($intervals as $interval) {
            if ($interval > $gapThreshold) {
                $gapCount++;
                continue;
            }
            $nonGapIntervals++;
            if ($interval >= $minDays && $interval <= $maxDays) {
                $matchingIntervals++;
            }
        }

        if ($nonGapIntervals === 0) {
            return 0.0;
        }

        $baseConsistency = $matchingIntervals / $nonGapIntervals;

        // Apply gap penalty: many gaps reduce confidence without completely killing it
        $gapRatio = $gapCount / count($intervals);
        return $baseConsistency * (1 - $gapRatio * 0.5);
    }

    /**
     * Calculate overall confidence score
     *
     * @param Transaction[] $transactions
     */
    private function calculateConfidence(
        array $transactions,
        RecurrenceFrequency $frequency,
        float $intervalConsistency
    ): float {
        // Occurrence score: more occurrences = higher confidence
        $minOccurrences = $frequency->getMinOccurrences();
        $idealOccurrences = $minOccurrences * 2;
        $occurrenceScore = min(1.0, count($transactions) / $idealOccurrences);

        // Interval consistency is already calculated
        $intervalScore = $intervalConsistency;

        // Amount consistency
        $amountScore = $this->calculateAmountConsistency($transactions);

        // Weighted average
        return (
            (self::WEIGHT_OCCURRENCE * $occurrenceScore) +
            (self::WEIGHT_INTERVAL * $intervalScore) +
            (self::WEIGHT_AMOUNT * $amountScore)
        );
    }

    /**
     * Calculate amount consistency (how similar amounts are)
     *
     * @param Transaction[] $transactions
     */
    private function calculateAmountConsistency(array $transactions): float
    {
        if (count($transactions) < 2) {
            return 1.0;
        }

        $amounts = array_map(
            fn(Transaction $t) => (int) $t->getAmount()->getAmount(),
            $transactions
        );

        $avg = array_sum($amounts) / count($amounts);
        if ($avg == 0) {
            return 1.0;
        }

        // Calculate coefficient of variation (lower = more consistent)
        $variance = 0;
        foreach ($amounts as $amount) {
            $variance += pow($amount - $avg, 2);
        }
        $stdDev = sqrt($variance / count($amounts));
        $coefficientOfVariation = $stdDev / abs($avg);

        // Convert to a score (0 to 1, where 1 is perfectly consistent)
        // CV of 0.1 (10% variation) = score of 0.9
        // CV of 0.5 (50% variation) = score of 0.5
        return max(0, 1 - $coefficientOfVariation);
    }

    /**
     * Calculate average amount and variance percentage
     *
     * @param Transaction[] $transactions
     * @return array{int, float} [averageAmountInCents, variancePercentage]
     */
    private function calculateAmountStats(array $transactions): array
    {
        $amounts = array_map(
            fn(Transaction $t) => (int) $t->getAmount()->getAmount(),
            $transactions
        );

        $avg = (int) round(array_sum($amounts) / count($amounts));

        if ($avg == 0) {
            return [0, 0.0];
        }

        // Calculate max deviation as percentage
        $maxDeviation = 0;
        foreach ($amounts as $amount) {
            $deviation = abs($amount - $avg) / abs($avg) * 100;
            $maxDeviation = max($maxDeviation, $deviation);
        }

        return [$avg, round($maxDeviation, 2)];
    }

    /**
     * Get the minimum number of transactions required in the recent window per frequency
     */
    private function getMinRecentOccurrences(RecurrenceFrequency $frequency): int
    {
        return match ($frequency) {
            RecurrenceFrequency::WEEKLY => 4,
            RecurrenceFrequency::BIWEEKLY => 2,
            RecurrenceFrequency::MONTHLY => 2,
            RecurrenceFrequency::QUARTERLY => 1,
            RecurrenceFrequency::YEARLY => 1,
        };
    }

    /**
     * Calculate variance percentage from a reference amount
     *
     * @param Transaction[] $transactions
     */
    private function calculateAmountVariance(array $transactions, int $referenceAmount): float
    {
        if ($referenceAmount == 0) {
            return 0.0;
        }

        $amounts = array_map(
            fn(Transaction $t) => (int) $t->getAmount()->getAmount(),
            $transactions
        );

        // Calculate max deviation as percentage from reference amount
        $maxDeviation = 0;
        foreach ($amounts as $amount) {
            $deviation = abs($amount - $referenceAmount) / abs($referenceAmount) * 100;
            $maxDeviation = max($maxDeviation, $deviation);
        }

        return round($maxDeviation, 2);
    }
}
