<?php

namespace App\Pattern\Service;

use App\Account\Repository\AccountRepository;
use App\Category\Repository\CategoryRepository;
use App\Entity\Pattern;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\DTO\PatternDTO;
use App\Pattern\DTO\UpdatePatternDTO;
use App\Pattern\Mapper\PatternMapper;
use App\Pattern\Repository\PatternRepository;
use App\SavingsAccount\Repository\SavingsAccountRepository;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class PatternService
{
    private PatternMapper $mapper;
    private PatternRepository $patternRepository;
    private AccountRepository $accountRepository;
    private CategoryRepository $categoryRepository;
    private SavingsAccountRepository $savingsAccountRepository;
    private PatternAssignService $assignService;
    public function __construct
    (
        PatternMapper $mapper,
        PatternRepository $patternRepository,
        AccountRepository $accountRepository,
        CategoryRepository $categoryRepository,
        SavingsAccountRepository $savingsAccountRepository,
        PatternAssignService $assignService
    )
    {
        $this->mapper = $mapper;
        $this->patternRepository = $patternRepository;
        $this->accountRepository = $accountRepository;
        $this->categoryRepository = $categoryRepository;
        $this->savingsAccountRepository = $savingsAccountRepository;
        $this->assignService = $assignService;
    }

    public function createFromDTO(CreatePatternDTO $dto): PatternDTO
    {
        $hash = $this->mapper->generateHash(
            $dto->accountId,
            $dto->description,
            $dto->notes,
            $dto->categoryId,
            $dto->savingsAccountId
        );
        if ($this->patternRepository->findByHash($hash)) {
            throw new BadRequestHttpException("Er bestaat al een identiek patroon.");
        }

        $account = $this->accountRepository->find($dto->accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account met ID $dto->accountId niet gevonden.");
        }

        $category = null;
        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if (!$category) {
                throw new NotFoundHttpException("Categorie met ID $dto->categoryId niet gevonden.");
            }
        }

        // Valideer dat category's transactionType matcht met pattern's transactionType
        if ($dto->transactionType !== null && $category !== null && $category->getTransactionType()->value !== $dto->transactionType) {
            $patternTypeNL = $dto->transactionType === TransactionType::DEBIT ? 'uitgaven' : 'inkomsten';
            $categoryTypeNL = $category->getTransactionType() === TransactionType::DEBIT ? 'uitgaven' : 'inkomsten';

            throw new BadRequestHttpException(
                "Categorie '{$category->getName()}' is voor {$categoryTypeNL}, maar dit patroon is voor {$patternTypeNL}."
            );
        }

        $savingsAccount = null;
        if ($dto->savingsAccountId !== null) {
            $savingsAccount = $this->savingsAccountRepository->find($dto->savingsAccountId);
            if (!$savingsAccount) {
                throw new NotFoundHttpException("Spaarrekening met ID $dto->savingsAccountId niet gevonden.");
            }
        }

        if ($savingsAccount && $savingsAccount->getAccount()->getId() !== $account->getId()) {
            throw new NotFoundHttpException("Spaarrekening behoort niet tot dit account.");
        }

        $pattern = $this->mapper->fromCreateDto($dto, $account, $category, $savingsAccount);
        $this->patternRepository->save($pattern);
        $this->assignService->assignSinglePattern($pattern);

        return $this->mapper->toDto($pattern);
    }

    public function updateFromDTO(int $patternId, UpdatePatternDTO $dto): PatternDTO
    {
        $pattern = $this->patternRepository->find($patternId);
        if (!$pattern) {
            throw new NotFoundHttpException("Pattern met ID $patternId niet gevonden.");
        }

        if ($pattern->getAccount()->getId() !== $dto->accountId) {
            throw new AccessDeniedHttpException("Pattern hoort niet bij het opgegeven account.");
        }

        if ($dto->categoryId !== null) {
            $category = $this->categoryRepository->find($dto->categoryId);
            if (!$category) {
                throw new NotFoundHttpException("Categorie met ID $dto->categoryId niet gevonden.");
            }

            // Valideer dat category's transactionType matcht met pattern's transactionType
            if ($dto->transactionType !== null && $category !== null && $category->getTransactionType()->value !== $dto->transactionType) {
                $patternTypeNL = $dto->transactionType === TransactionType::DEBIT ? 'uitgaven' : 'inkomsten';
                $categoryTypeNL = $category->getTransactionType() === TransactionType::DEBIT ? 'uitgaven' : 'inkomsten';

                throw new BadRequestHttpException(
                    "Categorie '{$category->getName()}' is voor {$categoryTypeNL}, maar dit patroon is voor {$patternTypeNL}."
                );
            }

            $pattern->setCategory($category);
        }

        if ($dto->savingsAccountId !== null) {
            $savingsAccount = $this->savingsAccountRepository->find($dto->savingsAccountId);
            if (!$savingsAccount) {
                throw new NotFoundHttpException("Spaarrekening met ID $dto->savingsAccountId niet gevonden.");
            }

            if ($savingsAccount->getAccount()->getId() !== $dto->accountId) {
                throw new NotFoundHttpException("Spaarrekening behoort niet tot dit account.");
            }

            $pattern->setSavingsAccount($savingsAccount);
        }

        $this->mapper->updateFromDto($pattern, $dto);
        $this->patternRepository->save($pattern);

        return $this->mapper->toDto($pattern);
    }

    public function deletePattern(int $accountId, int $patternId): void
    {
        $pattern = $this->patternRepository->find($patternId);
        if (!$pattern) {
            throw new NotFoundHttpException("Pattern met ID $patternId niet gevonden.");
        }

        if ($pattern->getAccount()->getId() !== $accountId) {
            throw new AccessDeniedHttpException("Pattern hoort niet bij het opgegeven account.");
        }

        $this->patternRepository->remove($pattern);
    }

    // Al de GETs

    public function getByAccount(int $accountId): array
    {
        $patterns = $this->patternRepository->findByAccountId($accountId);
        return array_map(fn(Pattern $p) => $this->mapper->toDto($p), $patterns);
    }

    public function getByCategory(int $categoryId): array
    {
        $patterns = $this->patternRepository->findByCategoryId($categoryId);
        return array_map(fn(Pattern $p) => $this->mapper->toDto($p), $patterns);
    }

    public function getBySavingsAccount(int $savingsAccountId): array
    {
        $patterns = $this->patternRepository->findBySavingsAccountId($savingsAccountId);
        return array_map(fn(Pattern $p) => $this->mapper->toDto($p), $patterns);
    }

    public function getById(int $patternId): PatternDTO
    {
        $pattern = $this->patternRepository->find($patternId);

        if (!$pattern) {
            throw new NotFoundHttpException("Pattern met ID $patternId niet gevonden.");
        }

        return $this->mapper->toDto($pattern);
    }

    public function deleteWithoutCategory(int $accountId): int
    {
        $patterns = $this->patternRepository->findWithoutCategory($accountId);
        $count = count($patterns);

        foreach ($patterns as $pattern) {
            $this->patternRepository->remove($pattern, false);
        }

        $this->patternRepository->flush();

        return $count;
    }
}