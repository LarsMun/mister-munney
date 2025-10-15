<?php

namespace App\Transaction\Service;

use App\Account\Repository\AccountRepository;
use App\Category\Repository\CategoryRepository;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\SavingsAccount\Repository\SavingsAccountRepository;
use App\Transaction\DTO\TransactionFilterDTO;
use App\Transaction\Repository\TransactionRepository;
use DatePeriod;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Money\Money;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Service voor het ophalen en bewerken van transacties.
 *
 * Haalt transacties op en wijst savingsaccount en categorie toe.
 */
class TransactionService
{
    private EntityManagerInterface $entityManager;
    private TransactionRepository $transactionRepository;
    private SavingsAccountRepository $savingsAccountRepository;
    private CategoryRepository $categoryRepository;
    private AccountRepository $accountRepository;
    private MoneyFactory $moneyFactory;

    public function __construct(
        EntityManagerInterface $entityManager,
        TransactionRepository $transactionRepository,
        SavingsAccountRepository $savingsAccountRepository,
        CategoryRepository $categoryRepository,
        AccountRepository $accountRepository,
        MoneyFactory $moneyFactory
    )
    {
        $this->entityManager = $entityManager;
        $this->transactionRepository = $transactionRepository;
        $this->savingsAccountRepository = $savingsAccountRepository;
        $this->categoryRepository = $categoryRepository;
        $this->accountRepository = $accountRepository;
        $this->moneyFactory = $moneyFactory;
    }

    public function findWithSummary(TransactionFilterDTO $filter): array
    {
        $transactions = $this->transactionRepository->findByFilter($filter);
        $summary = $this->transactionRepository->summaryByFilter($filter);

        $sortedTransactions = $transactions;
        // Sorteer transacties op datum oplopend
        usort($sortedTransactions, function (Transaction $a, Transaction $b) {
            $dateComparison = $a->getDate() <=> $b->getDate();
            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            // Als de datum gelijk is, sorteer op id **aflopend**
            return $b->getId() <=> $a->getId();
        });

        $summary = $this->addBalancesToSummary($sortedTransactions, $summary);
        $treeMapData = $this->generateTreeMapData($sortedTransactions);

        return [
            'summary' => $summary,
            'treeMapData' => $treeMapData,
            'data' => $transactions,
        ];
    }

    private function generateTreeMapData(array $transactions): array
    {
        $debitCategories = [];
        $creditCategories = [];
        $totalDebitAmount = 0;
        $totalCreditAmount = 0;

        foreach ($transactions as $transaction) {

            $category = $transaction->getCategory();
            if (!$category) {
                $categoryId = 0;
                $categoryName = 'Niet ingedeeld';
                $color = '#CCCCCC';
                $icon = 'help-circle';
            } else {
                $categoryId = $category->getId();
                $categoryName = $category->getName();
                $color = $category->getColor();
                $icon = $category->getIcon();
            }

            $amount = $transaction->getAmount();
            $floatAmount = $this->moneyFactory->toFloat($amount);
            $isDebit = $transaction->isDebit();

            if ($isDebit) {
                $totalDebitAmount += $floatAmount;
            } else {
                $totalCreditAmount += $floatAmount;
            }

            if ($isDebit) {
                if (!isset($debitCategories[$categoryName])) {
                    $debitCategories[$categoryName] = [
                        'categoryId' => $categoryId,
                        'categoryName' => $categoryName,
                        'transactionCount' => 0,
                        'totalAmount' => 0.0,
                        'categoryColor' => $color,
                        'categoryIcon' => $icon,
                    ];
                }

                $debitCategories[$categoryName]['transactionCount']++;
                $debitCategories[$categoryName]['totalAmount'] += $floatAmount;
            } else {
                if (!isset($creditCategories[$categoryName])) {
                    $creditCategories[$categoryName] = [
                        'categoryId' => $categoryId,
                        'categoryName' => $categoryName,
                        'transactionCount' => 0,
                        'totalAmount' => 0.0,
                        'categoryColor' => $color,
                        'categoryIcon' => $icon,
                    ];
                }

                $creditCategories[$categoryName]['transactionCount']++;
                $creditCategories[$categoryName]['totalAmount'] += $floatAmount;
            }
        }

        foreach ($debitCategories as &$debit) {
            $debit['percentageOfTotal'] = $totalDebitAmount > 0 ? round(($debit['totalAmount'] / $totalDebitAmount) * 100, 2) : 0;
        }
        foreach ($creditCategories as &$credit) {
            $credit['percentageOfTotal'] = $totalCreditAmount > 0 ? round(($credit['totalAmount'] / $totalCreditAmount) * 100, 2) : 0;
        }

        uasort($debitCategories, fn($a, $b) => $b['percentageOfTotal'] <=> $a['percentageOfTotal']);
        uasort($creditCategories, fn($a, $b) => $b['percentageOfTotal'] <=> $a['percentageOfTotal']);

        return [
            'debit' => array_values($debitCategories),
            'credit' => array_values($creditCategories),
        ];
    }

    private function addBalancesToSummary(array $transactions, array $summary): array
    {
        if (empty($transactions)) {
            $summary['start_balance'] = 0.00;
            $summary['end_balance'] = 0.00;
            $summary['daily'] = [];
            $summary['daily_balances'] = [];
            $summary['daily_total_credits'] = [];
            $summary['daily_total_debits'] = [];
            return $summary;
        }

        [$startDate, $endDate] = $this->getDateRange($transactions);
        $period = $this->createDatePeriod($startDate, $endDate);

        $daily = [];
        $dailyBalances = [];
        $dailyCredits = [];
        $dailyDebits = [];

        $initialBalance = $this->calculateInitialBalance($transactions[0]);
        $lastBalance = $initialBalance;
        $transactionIndex = 0;
        $transactionCount = count($transactions);

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $dayCredits = $this->moneyFactory->zero();
            $dayDebits = $this->moneyFactory->zero();

            while ($transactionIndex < $transactionCount && $transactions[$transactionIndex]->getDate()->format('Y-m-d') === $dateString) {
                $transaction = $transactions[$transactionIndex];

                if ($transaction->getTransactionType() === TransactionType::CREDIT) {
                    $dayCredits = $dayCredits->add($transaction->getAmount());
                } else {
                    $dayDebits = $dayDebits->add($transaction->getAmount());
                }

                $lastBalance = $transaction->getBalanceAfter();
                $transactionIndex++;
            }

            $daily[] = [
                'date' => $dateString,
                'value' => $this->moneyFactory->toFloat($lastBalance),
                'debitTotal' => $this->moneyFactory->toFloat($dayDebits),
                'creditTotal' => $this->moneyFactory->toFloat($dayCredits),
            ];

            // Vul ook de losse velden
            $dailyBalances[$dateString] = $this->moneyFactory->toFloat($lastBalance);
            $dailyCredits[$dateString] = $this->moneyFactory->toFloat($dayCredits);
            $dailyDebits[$dateString] = $this->moneyFactory->toFloat($dayDebits);
        }

        $summary['start_balance'] = $this->moneyFactory->toFloat($initialBalance);
        $summary['end_balance'] = $this->moneyFactory->toFloat($lastBalance);
        $summary['daily'] = $daily;
        $summary['daily_balances'] = $dailyBalances;
        $summary['daily_total_credits'] = $dailyCredits;
        $summary['daily_total_debits'] = $dailyDebits;

        return $summary;
    }

    private function getDateRange(array $transactions): array
    {
        $firstDate = (new DateTimeImmutable($transactions[0]->getDate()->format('Y-m-01')));
        $lastDate = (new DateTimeImmutable($transactions[array_key_last($transactions)]->getDate()->format('Y-m-t')));
        return [$firstDate, $lastDate];
    }

    private function createDatePeriod(DateTimeImmutable $start, DateTimeImmutable $end): DatePeriod
    {
        return new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
    }

    private function calculateInitialBalance(Transaction $transaction): Money
    {
        $balanceAfter = $transaction->getBalanceAfter();
        $amount = $transaction->getAmount();

        return $transaction->getTransactionType() === 'credit'
            ? $balanceAfter->subtract($amount)
            : $balanceAfter->add($amount);
    }

    public function getByCategory(int $categoryId): array
    {
        return $this->transactionRepository->findBy(['category' => $categoryId]);
    }

    public function getAvailableMonths(int $accountId): array
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account niet gevonden");
        }
        return $this->transactionRepository->findAvailableMonths($accountId);
    }

    /**
     * Wijst handmatig een categorie toe aan een bestaande transactie.
     *
     * @param int $transactionId ID van de transactie
     * @param int $categoryId ID van de categorie
     * @return Transaction De bijgewerkte transactie
     *
     * @throws InvalidArgumentException Als ID's niet bestaan
     */
    public function setCategory(int $transactionId, int $categoryId): Transaction
    {
        $transaction = $this->transactionRepository->find($transactionId);
        if (!$transaction) {
            throw new NotFoundHttpException("Transactie niet gevonden");
        }

        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            throw new NotFoundHttpException("Categorie niet gevonden");
        }

        // Categories can now contain both CREDIT and DEBIT transactions
        // No validation needed for transaction type

        $transaction->setCategory($category);

        $this->entityManager->flush();
        return $transaction;
    }

    public function removeCategory(int $transactionId): void
    {
        $transaction = $this->transactionRepository->find($transactionId);

        if (!$transaction) {
            throw new NotFoundHttpException('Transactie niet gevonden');
        }

        $transaction->setCategory(null);

        $this->transactionRepository->save($transaction);
    }

    public function bulkAssignCategory(array $transactionIds, int $categoryId): void
    {
        // Haal de categorie op
        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            throw new NotFoundHttpException("Categorie niet gevonden");
        }

        // Haal alle transacties op
        $transactions = $this->transactionRepository->findBy(['id' => $transactionIds]);

        if (empty($transactions)) {
            throw new NotFoundHttpException("Geen transacties gevonden");
        }

        // Categories can now contain both CREDIT and DEBIT transactions
        // No validation needed for transaction type - voer de bulk update uit
        $this->transactionRepository->bulkAssignCategory($transactionIds, $categoryId);
    }

    public function bulkRemoveCategory(array $transactionIds): void
    {
        $this->transactionRepository->bulkRemoveCategory($transactionIds);
    }

    /**
     * Wijst handmatig een spaarrekening toe aan een bestaande transactie.
     *
     * @param int $transactionId ID van de transactie
     * @param int $savingsAccountId ID van de spaarrekening
     * @return Transaction De bijgewerkte transactie
     *
     * @throws InvalidArgumentException Als ID's niet bestaan
     */
    public function setSavingsAccount(int $transactionId, int $savingsAccountId): Transaction
    {
        if (empty($savingsAccountId)) {
            throw new BadRequestHttpException('savingsAccountId is verplicht.');
        }

        $transaction = $this->transactionRepository->find($transactionId);
        if (!$transaction) {
            throw new NotFoundHttpException("Transactie met ID $transactionId niet gevonden.");
        }

        $savingsAccount = $this->savingsAccountRepository->find($savingsAccountId);
        if (!$savingsAccount) {
            throw new NotFoundHttpException("Spaarrekening met ID $savingsAccountId niet gevonden.");
        }

        $transaction->setSavingsAccount($savingsAccount);
        $this->entityManager->flush();

        return $transaction;
    }

    /**
     * Berekent verschillende statistieken van maandelijkse uitgaven.
     *
     * @param int $accountId
     * @param string|int $months 'all' of een getal voor aantal maanden
     * @return array
     * @throws NotFoundHttpException
     */
    public function getMonthlyMedianStatistics(int $accountId, string|int $months): array
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account niet gevonden");
        }

        // Bepaal het aantal maanden
        $monthLimit = null;
        if ($months !== 'all') {
            $monthLimit = is_numeric($months) ? (int)$months : null;
            if ($monthLimit !== null && $monthLimit < 1) {
                throw new BadRequestHttpException("Months parameter moet groter zijn dan 0 of 'all'");
            }
        }

        // Haal maandelijkse totalen op
        $monthlyData = $this->transactionRepository->getMonthlyDebitTotals($accountId, $monthLimit);

        if (empty($monthlyData)) {
            return [
                'median' => 0.00,
                'trimmedMean' => 0.00,
                'iqrMean' => 0.00,
                'weightedMedian' => 0.00,
                'plainAverage' => 0.00,
                'monthCount' => 0,
                'monthlyTotals' => [],
            ];
        }

        // Converteer naar cents array voor berekeningen
        $totalsInCents = array_map(fn($row) => (int)$row['total'], $monthlyData);

        // Bereken alle statistieken
        $median = $this->calculateMedian($totalsInCents);
        $trimmedMean = $this->calculateTrimmedMean($totalsInCents, 0.2); // 20% trim
        $iqrMean = $this->calculateIQRMean($totalsInCents);
        $weightedMedian = $this->calculateWeightedMedian($totalsInCents);
        $plainAverage = $this->calculatePlainAverage($totalsInCents);

        // Converteer alle bedragen naar float voor response
        $monthlyTotalsFormatted = array_map(function($row) {
            return [
                'month' => $row['month'],
                'total' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents((int)$row['total'])
                ),
            ];
        }, $monthlyData);

        return [
            'median' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int)$median)),
            'trimmedMean' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int)$trimmedMean)),
            'iqrMean' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int)$iqrMean)),
            'weightedMedian' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int)$weightedMedian)),
            'plainAverage' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int)$plainAverage)),
            'monthCount' => count($totalsInCents),
            'monthlyTotals' => $monthlyTotalsFormatted,
        ];
    }

    /**
     * Berekent de mediaan van een array getallen
     */
    private function calculateMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        if ($count % 2 === 0) {
            return ($sorted[$count / 2 - 1] + $sorted[$count / 2]) / 2;
        }

        return $sorted[floor($count / 2)];
    }

    /**
     * Berekent trimmed mean: verwijdert hoogste en laagste percentage en berekent gemiddelde
     *
     * @param array $values
     * @param float $trimPercentage Percentage om te trimmen (0.2 = 20% aan beide kanten)
     */
    private function calculateTrimmedMean(array $values, float $trimPercentage = 0.2): float
    {
        if (empty($values)) {
            return 0;
        }

        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        // Bij weinig data, geen trimming toepassen
        if ($count < 4) {
            return array_sum($sorted) / $count;
        }

        // Bereken hoeveel waarden aan elke kant te verwijderen
        $trimCount = (int)floor($count * $trimPercentage);

        // Verwijder hoogste en laagste waarden
        $trimmed = array_slice($sorted, $trimCount, $count - (2 * $trimCount));

        return empty($trimmed) ? 0 : array_sum($trimmed) / count($trimmed);
    }

    /**
     * Berekent mean na het verwijderen van outliers via IQR methode
     */
    private function calculateIQRMean(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        $sorted = $values;
        sort($sorted);
        $count = count($sorted);

        // Bij weinig data, gewoon gemiddelde
        if ($count < 4) {
            return array_sum($sorted) / $count;
        }

        // Bereken Q1, Q3 en IQR
        $q1Index = floor($count * 0.25);
        $q3Index = floor($count * 0.75);

        $q1 = $sorted[$q1Index];
        $q3 = $sorted[$q3Index];
        $iqr = $q3 - $q1;

        // Bepaal grenzen voor outliers
        $lowerBound = $q1 - (1.5 * $iqr);
        $upperBound = $q3 + (1.5 * $iqr);

        // Filter outliers
        $filtered = array_filter($sorted, fn($v) => $v >= $lowerBound && $v <= $upperBound);

        return empty($filtered) ? 0 : array_sum($filtered) / count($filtered);
    }

    /**
     * Berekent gewogen mediaan waarbij recente maanden zwaarder wegen
     */
    private function calculateWeightedMedian(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        $count = count($values);

        // Bij weinig data, gewone mediaan
        if ($count < 3) {
            return $this->calculateMedian($values);
        }

        // Maak gewogen lijst (nieuwste maanden krijgen hogere gewichten)
        $weighted = [];
        foreach ($values as $index => $value) {
            // Lineaire weging: meest recente (index 0) krijgt hoogste gewicht
            $weight = $count - $index;
            for ($i = 0; $i < $weight; $i++) {
                $weighted[] = $value;
            }
        }

        return $this->calculateMedian($weighted);
    }

    /**
     * Berekent gewoon gemiddelde
     */
    private function calculatePlainAverage(array $values): float
    {
        if (empty($values)) {
            return 0;
        }

        return array_sum($values) / count($values);
    }
}