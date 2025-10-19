<?php

namespace App\Pattern\Mapper;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Pattern;
use App\Entity\SavingsAccount;
use App\Enum\MatchType;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\DTO\PatternDTO;
use App\Pattern\DTO\UpdatePatternDTO;
use App\Pattern\Service\PatternService;
use Money\Money;

class PatternMapper
{
    private  MoneyFactory $moneyFactory;
    public function __construct (MoneyFactory $moneyFactory) {
        $this->moneyFactory = $moneyFactory;
    }
    public function toDto(Pattern $pattern): PatternDTO
    {
        $dto = new PatternDTO();
        $dto->id = $pattern->getId();
        $dto->accountId = $pattern->getAccount()->getId();
        $dto->startDate = $pattern->getStartDate();
        $dto->endDate = $pattern->getEndDate();
        $dto->minAmount = $pattern->getMinAmount() !== null
            ? $this->moneyFactory->toFloat($pattern->getMinAmount())
            : null;
        $dto->maxAmount = $pattern->getMaxAmount() !== null
            ? $this->moneyFactory->toFloat($pattern->getMaxAmount())
            : null;
        $dto->transactionType = $pattern->getTransactionType()?->value;
        $dto->description = $pattern->getDescription();
        $dto->notes = $pattern->getNotes();
        $dto->tag = $pattern->getTag();
        $dto->strict = $pattern->isStrict();
        $dto->matchTypeDescription = $pattern->getMatchTypeDescription()->value;
        $dto->matchTypeNotes = $pattern->getMatchTypeNotes()->value;
        $dto->category = $pattern->getCategory() ? [
            'id' => $pattern->getCategory()->getId(),
            'name' => $pattern->getCategory()->getName(),
            'color' => $pattern->getCategory()->getColor(),
        ] : null;

        $dto->savingsAccount = $pattern->getSavingsAccount() ? [
            'id' => $pattern->getSavingsAccount()->getId(),
            'name' => $pattern->getSavingsAccount()->getName(),
            'color' => $pattern->getSavingsAccount()->getColor(),
        ] : null;

        return $dto;
    }

    public function fromCreateDto(
        CreatePatternDTO $dto,
        Account $account,
        ?Category $category = null,
        ?SavingsAccount $savingsAccount = null
    ): Pattern {
        $pattern = new Pattern();
        $pattern->setAccount($account);
        $pattern->setStartDate($dto->startDate ? new \DateTime($dto->startDate) : null);
        $pattern->setEndDate($dto->endDate ? new \DateTime($dto->endDate) : null);
        $pattern->setMinAmount($dto->minAmount !== null
            ? $this->moneyFactory->fromFloat($dto->minAmount)
            : null);
        $pattern->setMaxAmount($dto->maxAmount !== null
            ? $this->moneyFactory->fromFloat($dto->maxAmount)
            : null);
        $pattern->setTransactionType($dto->transactionType ? TransactionType::from($dto->transactionType) : null);
        $pattern->setDescription($dto->description);
        $pattern->setMatchTypeDescription(MatchType::fromInput($dto->matchTypeDescription ?? 'LIKE'));
        $pattern->setNotes($dto->notes);
        $pattern->setMatchTypeNotes(MatchType::fromInput($dto->matchTypeNotes ?? 'LIKE'));
        $pattern->setTag($dto->tag);
        $pattern->setStrict($dto->strict);
        $pattern->setUniqueHash(
            $this->generateHash(
                $account->getId(),
                $dto->description,
                $dto->notes,
                $dto->categoryId,
                $dto->savingsAccountId
            )
        );
        $pattern->setCategory($category);
        $pattern->setSavingsAccount($savingsAccount);

        return $pattern;
    }

    public function updateFromDto(Pattern $pattern, UpdatePatternDTO $dto): void
    {
        $pattern->setStartDate($dto->startDate ? new \DateTime($dto->startDate) : null);
        $pattern->setEndDate($dto->endDate ? new \DateTime($dto->endDate) : null);
        $pattern->setMinAmount($dto->minAmount !== null
            ? $this->moneyFactory->fromFloat($dto->minAmount)
            : null);
        $pattern->setMaxAmount($dto->maxAmount !== null
            ? $this->moneyFactory->fromFloat($dto->maxAmount)
            : null);
        $pattern->setTransactionType($dto->transactionType ? TransactionType::from($dto->transactionType) : null);
        $pattern->setDescription($dto->description);
        $pattern->setMatchTypeDescription(MatchType::fromInput($dto->matchTypeDescription ?? 'LIKE'));
        $pattern->setNotes($dto->notes);
        $pattern->setMatchTypeNotes(MatchType::fromInput($dto->matchTypeNotes ?? 'LIKE'));
        $pattern->setTag($dto->tag);
        $pattern->setStrict($dto->strict);
    }

    public function generateHash(
        int $accountId,
        ?string $description,
        ?string $notes,
        ?int $categoryId,
        ?int $savingsAccountId
    ): string {
        $parts = [
            $accountId,
            strtolower(trim($description ?? '')),
            strtolower(trim($notes ?? '')),
            $categoryId ?? 0,
            $savingsAccountId ?? 0,
        ];

        return hash('sha256', implode('|', $parts));
    }
}