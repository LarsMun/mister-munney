<?php

namespace App\Tests\Unit\Transaction\Service;

use App\Category\Repository\CategoryRepository;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Money\MoneyFactory;
use App\SavingsAccount\Repository\SavingsAccountRepository;
use App\Transaction\Repository\TransactionRepository;
use App\Transaction\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TransactionServiceTest extends TestCase
{
    private TransactionService $transactionService;
    private MockObject $entityManager;
    private MockObject $transactionRepository;
    private MockObject $categoryRepository;
    private MockObject $savingsAccountRepository;
    private MoneyFactory $moneyFactory;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->savingsAccountRepository = $this->createMock(SavingsAccountRepository::class);
        $this->moneyFactory = new MoneyFactory();

        $this->transactionService = new TransactionService(
            $this->entityManager,
            $this->transactionRepository,
            $this->savingsAccountRepository,
            $this->categoryRepository,
            $this->createMock(\App\Account\Repository\AccountRepository::class),
            $this->moneyFactory
        );
    }

    public function testSetCategoryUpdatesTransaction(): void
    {
        // Given
        $transactionId = 1;
        $categoryId = 2;

        $transaction = $this->createMock(Transaction::class);
        $category = $this->createMock(Category::class);

        $this->transactionRepository->expects($this->once())
            ->method('find')
            ->with($transactionId)
            ->willReturn($transaction);

        $this->categoryRepository->expects($this->once())
            ->method('find')
            ->with($categoryId)
            ->willReturn($category);

        $transaction->expects($this->once())
            ->method('setCategory')
            ->with($category);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->transactionService->setCategory($transactionId, $categoryId);

        // Then
        $this->assertSame($transaction, $result);
    }

    public function testSetCategoryThrowsExceptionWhenTransactionNotFound(): void
    {
        // Given
        $transactionId = 999;
        $categoryId = 2;

        $this->transactionRepository->expects($this->once())
            ->method('find')
            ->with($transactionId)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Transactie niet gevonden');

        // When
        $this->transactionService->setCategory($transactionId, $categoryId);
    }

    public function testSetCategoryThrowsExceptionWhenCategoryNotFound(): void
    {
        // Given
        $transactionId = 1;
        $categoryId = 999;

        $transaction = $this->createMock(Transaction::class);

        $this->transactionRepository->expects($this->once())
            ->method('find')
            ->with($transactionId)
            ->willReturn($transaction);

        $this->categoryRepository->expects($this->once())
            ->method('find')
            ->with($categoryId)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Categorie niet gevonden');

        // When
        $this->transactionService->setCategory($transactionId, $categoryId);
    }
}