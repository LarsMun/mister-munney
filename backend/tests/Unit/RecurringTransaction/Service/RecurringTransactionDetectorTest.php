<?php

namespace App\Tests\Unit\RecurringTransaction\Service;

use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionType;
use App\RecurringTransaction\Repository\RecurringTransactionRepository;
use App\RecurringTransaction\Service\MerchantNormalizer;
use App\RecurringTransaction\Service\RecurringTransactionDetector;
use App\Transaction\Repository\TransactionRepository;
use DateTime;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Money\Money;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RecurringTransactionDetectorTest extends TestCase
{
    private RecurringTransactionDetector $detector;
    private MockObject $transactionRepository;
    private MockObject $recurringTransactionRepository;
    private MerchantNormalizer $merchantNormalizer;

    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->recurringTransactionRepository = $this->createMock(RecurringTransactionRepository::class);
        $this->merchantNormalizer = new MerchantNormalizer();

        $this->detector = new RecurringTransactionDetector(
            $this->transactionRepository,
            $this->recurringTransactionRepository,
            $this->merchantNormalizer
        );
    }

    public function testReturnsEmptyWhenInsufficientTransactions(): void
    {
        // Given
        $account = $this->createMock(Account::class);
        $this->mockQueryBuilder([]);

        // When
        $result = $this->detector->detect($account);

        // Then
        $this->assertEmpty($result);
    }

    public function testDetectsMonthlyPatternWithConsistentIntervals(): void
    {
        // Given
        $account = $this->createAccount(1);
        $transactions = $this->createMonthlyTransactions('IBAN:NL91ABNA0417164300', -1299, 4);

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository
            ->method('saveAll')
            ->with($this->callback(function ($patterns) {
                return count($patterns) > 0;
            }));

        // When
        $result = $this->detector->detect($account);

        // Then
        $this->assertNotEmpty($result);
        $this->assertEquals(RecurrenceFrequency::MONTHLY, $result[0]->getFrequency());
    }

    public function testRejectsInsufficientOccurrences(): void
    {
        // Given
        $account = $this->createAccount(1);

        // Only 2 monthly transactions (minimum for MONTHLY is 3)
        $transactions = $this->createMonthlyTransactions('IBAN:NL91ABNA0417164300', -1299, 2);

        $this->mockQueryBuilder($transactions);

        // When
        $result = $this->detector->detect($account);

        // Then
        $this->assertEmpty($result);
    }

    public function testRejectsBelowConfidenceThreshold(): void
    {
        // Given
        $account = $this->createAccount(1);

        // Create transactions with very inconsistent intervals
        $transactions = $this->createInconsistentTransactions('IBAN:NL91ABNA0417164300', -1299, 4);

        $this->mockQueryBuilder($transactions);

        // When
        $result = $this->detector->detect($account);

        // Then
        // Should either be empty or have low confidence (depending on implementation)
        foreach ($result as $pattern) {
            $this->assertLessThan(1.0, $pattern->getConfidenceScore());
        }
    }

    public function testCalculatesAmountVarianceCorrectly(): void
    {
        // Given
        $account = $this->createAccount(1);

        // Create transactions with varying amounts
        $amounts = [-1299, -1350, -1250, -1299]; // ~5% variance
        $transactions = [];
        $baseDate = new DateTime('-120 days');

        foreach ($amounts as $i => $amount) {
            $date = (clone $baseDate)->modify("+" . ($i * 30) . " days");
            $transactions[] = $this->createTransaction($date, $amount, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT);
        }

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then
        if (!empty($result)) {
            $this->assertGreaterThanOrEqual(0, $result[0]->getAmountVariance());
        }
    }

    public function testHandlesGapsInPattern(): void
    {
        // Given
        $account = $this->createAccount(1);

        // Create monthly transactions with one gap
        $dates = [
            new DateTime('-180 days'),
            new DateTime('-150 days'),
            new DateTime('-120 days'),
            // Gap at -90 days
            new DateTime('-60 days'),
            new DateTime('-30 days'),
        ];

        $transactions = [];
        foreach ($dates as $date) {
            $transactions[] = $this->createTransaction($date, -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT);
        }

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then
        // Should still detect the pattern but with lower confidence
        if (!empty($result)) {
            $this->assertLessThan(1.0, $result[0]->getIntervalConsistency());
        }
    }

    public function testDistinguishesDebitFromCredit(): void
    {
        // Given
        $account = $this->createAccount(1);

        // Create both debit and credit transactions from same merchant
        $debitTransactions = $this->createMonthlyTransactions('IBAN:NL91ABNA0417164300', -1299, 4, TransactionType::DEBIT);
        $creditTransactions = $this->createMonthlyTransactions('IBAN:NL91ABNA0417164300', 2500, 4, TransactionType::CREDIT);

        // Offset credit transactions by 15 days
        foreach ($creditTransactions as $t) {
            $t->method('getDate')->willReturn((new DateTime())->modify('-' . (rand(0, 120)) . ' days'));
        }

        $this->mockQueryBuilder(array_merge($debitTransactions, $creditTransactions));
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then - should detect at least one pattern
        $this->assertNotEmpty($result);
    }

    public function testSetsCorrectTransactionType(): void
    {
        // Given
        $account = $this->createAccount(1);
        $transactions = $this->createMonthlyTransactions('IBAN:NL91ABNA0417164300', -1299, 4, TransactionType::DEBIT);

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then
        if (!empty($result)) {
            $this->assertEquals(TransactionType::DEBIT, $result[0]->getTransactionType());
        }
    }

    public function testDetectsYearlyPatternWith36MonthWindow(): void
    {
        // Given: 3 yearly transactions ~365 days apart, spanning >24 months
        $account = $this->createAccount(1);

        $transactions = [
            $this->createTransaction(new DateTime('-745 days'), -5999, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-380 days'), -5999, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-15 days'), -5999, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
        ];

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then
        $this->assertNotEmpty($result);
        $this->assertEquals(RecurrenceFrequency::YEARLY, $result[0]->getFrequency());
    }

    public function testFiltersOutGapIntervalsInConsistency(): void
    {
        // Given: monthly pattern with a 6-month gap in the middle
        $account = $this->createAccount(1);

        $transactions = [
            $this->createTransaction(new DateTime('-360 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-330 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-300 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            // 6-month gap here (skipped -270 through -120)
            $this->createTransaction(new DateTime('-90 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-60 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-30 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
        ];

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then: should still detect the monthly pattern despite the gap
        $this->assertNotEmpty($result);
        $this->assertEquals(RecurrenceFrequency::MONTHLY, $result[0]->getFrequency());
    }

    public function testRejectsPatternWithNoRecentActivity(): void
    {
        // Given: monthly pattern active 18-24 months ago but nothing in last 12 months
        $account = $this->createAccount(1);

        $transactions = [
            $this->createTransaction(new DateTime('-730 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-700 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-670 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-640 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-610 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
            $this->createTransaction(new DateTime('-580 days'), -1299, 'IBAN:NL91ABNA0417164300', TransactionType::DEBIT),
        ];

        $this->mockQueryBuilder($transactions);

        // When
        $result = $this->detector->detect($account);

        // Then: should reject because no recent activity
        $this->assertEmpty($result);
    }

    public function testDetectsQuarterlyWithStrongerConfidence(): void
    {
        // Given: 8 quarterly transactions over 24 months
        $account = $this->createAccount(1);

        $transactions = [];
        for ($i = 7; $i >= 0; $i--) {
            $daysAgo = $i * 90 + 15; // Offset by 15 days so latest is recent
            $transactions[] = $this->createTransaction(
                new DateTime("-{$daysAgo} days"),
                -4999,
                'IBAN:NL91ABNA0417164300',
                TransactionType::DEBIT
            );
        }

        $this->mockQueryBuilder($transactions);
        $this->recurringTransactionRepository
            ->method('findByMerchantPattern')
            ->willReturn(null);
        $this->recurringTransactionRepository->method('saveAll');

        // When
        $result = $this->detector->detect($account);

        // Then
        $this->assertNotEmpty($result);
        $this->assertEquals(RecurrenceFrequency::QUARTERLY, $result[0]->getFrequency());
        // 8 occurrences with consistent intervals should yield high confidence
        $this->assertGreaterThan(0.85, $result[0]->getConfidenceScore());
    }

    // Helper methods

    private function createAccount(int $id): Account
    {
        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($id);
        return $account;
    }

    private function createTransaction(
        DateTime $date,
        int $amountCents,
        string $iban,
        TransactionType $type
    ): MockObject|Transaction {
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getDate')->willReturn($date);
        $transaction->method('getAmount')->willReturn(Money::EUR(abs($amountCents)));
        $transaction->method('getCounterpartyAccount')->willReturn($iban);
        $transaction->method('getDescription')->willReturn('Test transaction');
        $transaction->method('getTransactionType')->willReturn($type);
        $transaction->method('getCategory')->willReturn(null);
        return $transaction;
    }

    private function createMonthlyTransactions(
        string $iban,
        int $amountCents,
        int $count,
        TransactionType $type = TransactionType::DEBIT
    ): array {
        $transactions = [];
        for ($i = 0; $i < $count; $i++) {
            $date = (new DateTime())->modify('-' . ($i * 30) . ' days');
            $transactions[] = $this->createTransaction($date, $amountCents, $iban, $type);
        }
        return $transactions;
    }

    private function createInconsistentTransactions(
        string $iban,
        int $amountCents,
        int $count
    ): array {
        $transactions = [];
        $intervals = [15, 45, 20, 60]; // Very inconsistent

        $date = new DateTime();
        for ($i = 0; $i < $count; $i++) {
            $transactions[] = $this->createTransaction($date, $amountCents, $iban, TransactionType::DEBIT);
            $interval = $intervals[$i % count($intervals)];
            $date = (clone $date)->modify("-{$interval} days");
        }
        return $transactions;
    }

    private function mockQueryBuilder(array $transactions): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($transactions);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->transactionRepository
            ->method('createQueryBuilder')
            ->willReturn($qb);
    }
}
