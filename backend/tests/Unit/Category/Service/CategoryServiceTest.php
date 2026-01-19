<?php

namespace App\Tests\Unit\Category\Service;

use App\Account\Repository\AccountRepository;
use App\Category\Mapper\CategoryMapper;
use App\Category\Repository\CategoryRepository;
use App\Category\Service\CategoryService;
use App\Entity\Account;
use App\Entity\Category;
use App\Money\MoneyFactory;
use App\Pattern\Repository\PatternRepository;
use App\Transaction\Repository\TransactionRepository;
use App\Transaction\Service\TransactionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryServiceTest extends TestCase
{
    private CategoryService $categoryService;
    private MockObject $categoryRepository;
    private MockObject $accountRepository;
    private MockObject $transactionService;
    private MockObject $transactionRepository;
    private MockObject $patternRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $categoryMapper = $this->createMock(CategoryMapper::class);
        $this->accountRepository = $this->createMock(AccountRepository::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->transactionRepository = $this->createMock(TransactionRepository::class);
        $this->patternRepository = $this->createMock(PatternRepository::class);
        $moneyFactory = new MoneyFactory();

        $this->categoryService = new CategoryService(
            $this->categoryRepository,
            $categoryMapper,
            $this->accountRepository,
            $this->transactionService,
            $this->transactionRepository,
            $this->patternRepository,
            $moneyFactory
        );
    }

    public function testCreateCategoryWithValidData(): void
    {
        // Given
        $accountId = 1;
        $data = ['name' => 'Groceries', 'color' => '#FF5733', 'icon' => 'shopping-cart'];

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->categoryRepository->expects($this->once())
            ->method('save');

        // When
        $result = $this->categoryService->create($accountId, $data);

        // Then
        $this->assertEquals('Groceries', $result->getName());
        $this->assertEquals('#FF5733', $result->getColor());
        // Icon gets prefixed with path
        $this->assertStringContainsString('shopping-cart', $result->getIcon());
    }

    public function testCreateCategoryThrowsExceptionWhenNameEmpty(): void
    {
        // Given
        $accountId = 1;
        $data = ['name' => ''];

        // Then
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Naam is verplicht.');

        // When
        $this->categoryService->create($accountId, $data);
    }

    public function testCreateCategoryThrowsExceptionWhenAccountNotFound(): void
    {
        // Given
        $accountId = 999;
        $data = ['name' => 'Test'];

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with($accountId)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account niet gevonden.');

        // When
        $this->categoryService->create($accountId, $data);
    }

    public function testCreateCategoryThrowsExceptionWhenDuplicate(): void
    {
        // Given
        $accountId = 1;
        $data = ['name' => 'Groceries'];

        $account = $this->createMock(Account::class);
        $existingCategory = $this->createMock(Category::class);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($existingCategory);

        // Then
        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Een categorie met deze naam bestaat al voor dit account.');

        // When
        $this->categoryService->create($accountId, $data);
    }

    public function testCreateCategoryReplacesWhiteColor(): void
    {
        // Given
        $accountId = 1;
        $data = ['name' => 'Test', 'color' => '#FFFFFF'];

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->categoryRepository->expects($this->once())
            ->method('save');

        // When
        $result = $this->categoryService->create($accountId, $data);

        // Then - color should not be white
        $this->assertNotEquals('#FFFFFF', $result->getColor());
        $this->assertNotEquals('#ffffff', $result->getColor());
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $result->getColor());
    }

    public function testCreateCategoryReplacesTooLightColor(): void
    {
        // Given - a color that's too light (FAFAFA has brightness ~250)
        $accountId = 1;
        $data = ['name' => 'Test', 'color' => '#FAFAFA'];

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->categoryRepository->expects($this->once())
            ->method('save');

        // When
        $result = $this->categoryService->create($accountId, $data);

        // Then - color should be replaced with a pastel color
        $this->assertNotEquals('#FAFAFA', $result->getColor());
    }

    public function testGetByIdThrowsExceptionWhenNotFound(): void
    {
        // Given
        $categoryId = 999;

        $this->categoryRepository->expects($this->once())
            ->method('getById')
            ->with($categoryId)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Categorie niet gevonden.');

        // When
        $this->categoryService->getById($categoryId);
    }

    public function testGetByIdThrowsExceptionWhenWrongAccount(): void
    {
        // Given
        $categoryId = 1;
        $accountId = 5;
        $wrongAccountId = 10;

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($wrongAccountId);

        $category = $this->createMock(Category::class);
        $category->method('getAccount')->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('getById')
            ->with($categoryId)
            ->willReturn($category);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Categorie behoort niet tot dit account.');

        // When
        $this->categoryService->getById($categoryId, $accountId);
    }

    public function testDeleteThrowsExceptionWhenTransactionsLinked(): void
    {
        // Given
        $categoryId = 1;
        $accountId = 1;

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        $category = $this->createMock(Category::class);
        $category->method('getAccount')->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('getById')
            ->willReturn($category);

        $this->transactionRepository->expects($this->once())
            ->method('count')
            ->willReturn(5);

        // Then
        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Cannot delete category with 5 linked transaction(s)');

        // When
        $this->categoryService->delete($categoryId, $accountId);
    }

    public function testMergeCategoriesThrowsExceptionWhenSameCategory(): void
    {
        // Given
        $categoryId = 1;
        $accountId = 1;

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        $category = $this->createMock(Category::class);
        $category->method('getAccount')->willReturn($account);

        $this->categoryRepository->expects($this->exactly(2))
            ->method('getById')
            ->willReturn($category);

        // Then
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot merge a category into itself');

        // When
        $this->categoryService->mergeCategories($categoryId, $categoryId, $accountId);
    }
}
