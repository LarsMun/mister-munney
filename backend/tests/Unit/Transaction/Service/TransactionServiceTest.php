<?php

namespace App\Tests\Unit\Transaction\Service;

use App\Category\Repository\CategoryRepository;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\SavingsAccount\Repository\SavingsAccountRepository;
use App\Transaction\Repository\TransactionRepository;
use App\Transaction\Service\TransactionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Money\Money;

class TransactionServiceTest extends TestCase
{
    private TransactionService $transactionService;
    private MockObject $entityManager;
    private MockObject $transactionRepository;
    private MockObject $categoryRepository;
    private MockObject $savingsAccountRepository;
    private MockObject $accountRepository;
    private MoneyFactory $moneyFactory;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->savingsAccountRepository = $this->createMock(SavingsAccountRepository::class);
        $this->accountRepository = $this->createMock(\App\Account\Repository\AccountRepository::class);
        $this->moneyFactory = new MoneyFactory();

        $this->transactionService = new TransactionService(
            $this->entityManager,
            $this->transactionRepository,
            $this->savingsAccountRepository,
            $this->categoryRepository,
            $this->accountRepository,
            $this->moneyFactory
        );
    }

    public function testSetCategoryUpdatesTransaction(): void
    {
        // Given
        $transactionId = 1;
        $categoryId = 2;

        // Create real objects instead of mocks (enums can't be mocked)
        $account = new Account();
        $account->setName('Test Account')
            ->setAccountNumber('NL91TEST')
            ->setIsDefault(true);

        $transaction = new Transaction();
        $transaction->setHash('test-hash')
            ->setDate(new \DateTime())
            ->setDescription('Test Transaction')
            ->setAccount($account)
            ->setTransactionType(TransactionType::DEBIT)
            ->setAmount(Money::EUR(1000))
            ->setMutationType('Test')
            ->setNotes('Test')
            ->setBalanceAfter(Money::EUR(1000));

        $category = new Category();
        $category->setName('Test Category')
            ->setAccount($account)
            ->setTransactionType(TransactionType::DEBIT);

        $this->transactionRepository->expects($this->once())
            ->method('find')
            ->with($transactionId)
            ->willReturn($transaction);

        $this->categoryRepository->expects($this->once())
            ->method('find')
            ->with($categoryId)
            ->willReturn($category);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->transactionService->setCategory($transactionId, $categoryId);

        // Then
        $this->assertSame($transaction, $result);
        $this->assertSame($category, $transaction->getCategory());
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

        $account = new Account();
        $account->setName('Test Account')
            ->setAccountNumber('NL91TEST')
            ->setIsDefault(true);

        $transaction = new Transaction();
        $transaction->setHash('test-hash')
            ->setDate(new \DateTime())
            ->setDescription('Test Transaction')
            ->setAccount($account)
            ->setTransactionType(TransactionType::DEBIT)
            ->setAmount(Money::EUR(1000))
            ->setMutationType('Test')
            ->setNotes('Test')
            ->setBalanceAfter(Money::EUR(1000));

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