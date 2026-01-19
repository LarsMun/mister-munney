<?php

namespace App\Tests\Unit\Pattern\Service;

use App\Account\Repository\AccountRepository;
use App\Category\Repository\CategoryRepository;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Pattern;
use App\Money\MoneyFactory;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\DTO\PatternDTO;
use App\Pattern\Mapper\PatternMapper;
use App\Pattern\Repository\PatternRepository;
use App\Pattern\Service\PatternAssignService;
use App\Pattern\Service\PatternService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PatternServiceTest extends TestCase
{
    private PatternService $patternService;
    private MockObject $patternMapper;
    private MockObject $patternRepository;
    private MockObject $accountRepository;
    private MockObject $categoryRepository;
    private MockObject $assignService;

    protected function setUp(): void
    {
        $this->patternMapper = $this->createMock(PatternMapper::class);
        $this->patternRepository = $this->createMock(PatternRepository::class);
        $this->accountRepository = $this->createMock(AccountRepository::class);
        $this->categoryRepository = $this->createMock(CategoryRepository::class);
        $this->assignService = $this->createMock(PatternAssignService::class);

        $this->patternService = new PatternService(
            $this->patternMapper,
            $this->patternRepository,
            $this->accountRepository,
            $this->categoryRepository,
            $this->assignService
        );
    }

    public function testCreateFromDTOSuccessfully(): void
    {
        // Given
        $dto = new CreatePatternDTO();
        $dto->accountId = 1;
        $dto->description = 'Albert Heijn';
        $dto->notes = null;
        $dto->categoryId = 2;

        $account = $this->createMock(Account::class);
        $category = $this->createMock(Category::class);
        $pattern = $this->createMock(Pattern::class);
        $patternDto = new PatternDTO();

        $this->patternMapper->expects($this->once())
            ->method('generateHash')
            ->with(1, 'Albert Heijn', null, 2)
            ->willReturn('unique-hash');

        $this->patternRepository->expects($this->once())
            ->method('findByHash')
            ->with('unique-hash')
            ->willReturn(null);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($category);

        $this->patternMapper->expects($this->once())
            ->method('fromCreateDto')
            ->with($dto, $account, $category)
            ->willReturn($pattern);

        $this->patternRepository->expects($this->once())
            ->method('save')
            ->with($pattern);

        $this->assignService->expects($this->once())
            ->method('assignSinglePattern')
            ->with($pattern);

        $this->patternMapper->expects($this->once())
            ->method('toDto')
            ->with($pattern)
            ->willReturn($patternDto);

        // When
        $result = $this->patternService->createFromDTO($dto);

        // Then
        $this->assertSame($patternDto, $result);
    }

    public function testCreateFromDTOThrowsExceptionWhenDuplicatePattern(): void
    {
        // Given
        $dto = new CreatePatternDTO();
        $dto->accountId = 1;
        $dto->description = 'Albert Heijn';
        $dto->notes = null;
        $dto->categoryId = 2;

        $existingPattern = $this->createMock(Pattern::class);

        $this->patternMapper->expects($this->once())
            ->method('generateHash')
            ->willReturn('duplicate-hash');

        $this->patternRepository->expects($this->once())
            ->method('findByHash')
            ->with('duplicate-hash')
            ->willReturn($existingPattern);

        // Then
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Er bestaat al een identiek patroon.');

        // When
        $this->patternService->createFromDTO($dto);
    }

    public function testCreateFromDTOThrowsExceptionWhenAccountNotFound(): void
    {
        // Given
        $dto = new CreatePatternDTO();
        $dto->accountId = 999;
        $dto->description = 'Test';
        $dto->notes = null;
        $dto->categoryId = null;

        $this->patternMapper->expects($this->once())
            ->method('generateHash')
            ->willReturn('some-hash');

        $this->patternRepository->expects($this->once())
            ->method('findByHash')
            ->willReturn(null);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Account met ID 999 niet gevonden.');

        // When
        $this->patternService->createFromDTO($dto);
    }

    public function testCreateFromDTOThrowsExceptionWhenCategoryNotFound(): void
    {
        // Given
        $dto = new CreatePatternDTO();
        $dto->accountId = 1;
        $dto->description = 'Test';
        $dto->notes = null;
        $dto->categoryId = 999;

        $account = $this->createMock(Account::class);

        $this->patternMapper->expects($this->once())
            ->method('generateHash')
            ->willReturn('some-hash');

        $this->patternRepository->expects($this->once())
            ->method('findByHash')
            ->willReturn(null);

        $this->accountRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($account);

        $this->categoryRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Categorie met ID 999 niet gevonden.');

        // When
        $this->patternService->createFromDTO($dto);
    }

    public function testDeletePatternSuccessfully(): void
    {
        // Given
        $accountId = 1;
        $patternId = 5;

        $account = $this->createMock(Account::class);
        $account->method('getId')->willReturn($accountId);

        $pattern = $this->createMock(Pattern::class);
        $pattern->method('getAccount')->willReturn($account);

        $this->patternRepository->expects($this->once())
            ->method('find')
            ->with($patternId)
            ->willReturn($pattern);

        $this->patternRepository->expects($this->once())
            ->method('remove')
            ->with($pattern);

        // When
        $this->patternService->deletePattern($accountId, $patternId);

        // Then - no exception thrown
        $this->assertTrue(true);
    }

    public function testDeletePatternThrowsExceptionWhenNotFound(): void
    {
        // Given
        $accountId = 1;
        $patternId = 999;

        $this->patternRepository->expects($this->once())
            ->method('find')
            ->with($patternId)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Pattern met ID 999 niet gevonden.');

        // When
        $this->patternService->deletePattern($accountId, $patternId);
    }

    public function testDeletePatternThrowsExceptionWhenWrongAccount(): void
    {
        // Given
        $accountId = 1;
        $patternId = 5;
        $wrongAccountId = 99;

        $wrongAccount = $this->createMock(Account::class);
        $wrongAccount->method('getId')->willReturn($wrongAccountId);

        $pattern = $this->createMock(Pattern::class);
        $pattern->method('getAccount')->willReturn($wrongAccount);

        $this->patternRepository->expects($this->once())
            ->method('find')
            ->with($patternId)
            ->willReturn($pattern);

        // Then
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Pattern hoort niet bij het opgegeven account.');

        // When
        $this->patternService->deletePattern($accountId, $patternId);
    }

    public function testGetByIdThrowsExceptionWhenNotFound(): void
    {
        // Given
        $patternId = 999;

        $this->patternRepository->expects($this->once())
            ->method('find')
            ->with($patternId)
            ->willReturn(null);

        // Then
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Pattern met ID 999 niet gevonden.');

        // When
        $this->patternService->getById($patternId);
    }

    public function testGetByAccountReturnsPatternDTOs(): void
    {
        // Given
        $accountId = 1;
        $pattern1 = $this->createMock(Pattern::class);
        $pattern2 = $this->createMock(Pattern::class);

        $dto1 = new PatternDTO();
        $dto1->id = 1;
        $dto2 = new PatternDTO();
        $dto2->id = 2;

        $this->patternRepository->expects($this->once())
            ->method('findByAccountId')
            ->with($accountId)
            ->willReturn([$pattern1, $pattern2]);

        $this->patternMapper->expects($this->exactly(2))
            ->method('toDto')
            ->willReturnOnConsecutiveCalls($dto1, $dto2);

        // When
        $result = $this->patternService->getByAccount($accountId);

        // Then
        $this->assertCount(2, $result);
        $this->assertSame($dto1, $result[0]);
        $this->assertSame($dto2, $result[1]);
    }

    public function testDeleteWithoutCategoryReturnsCount(): void
    {
        // Given
        $accountId = 1;
        $pattern1 = $this->createMock(Pattern::class);
        $pattern2 = $this->createMock(Pattern::class);
        $pattern3 = $this->createMock(Pattern::class);

        $this->patternRepository->expects($this->once())
            ->method('findWithoutCategory')
            ->with($accountId)
            ->willReturn([$pattern1, $pattern2, $pattern3]);

        $this->patternRepository->expects($this->exactly(3))
            ->method('remove')
            ->with($this->isInstanceOf(Pattern::class), false);

        $this->patternRepository->expects($this->once())
            ->method('flush');

        // When
        $result = $this->patternService->deleteWithoutCategory($accountId);

        // Then
        $this->assertEquals(3, $result);
    }
}
