<?php

namespace App\Category\Mapper;

use App\Category\DTO\CategoryDTO;
use App\Category\DTO\CategoryWithTransactionsDTO;
use App\Entity\Category;
use App\Transaction\Mapper\TransactionMapper;

class CategoryMapper
{
    private TransactionMapper $transactionMapper;

    public function __construct(TransactionMapper $transactionMapper)
    {
        $this->transactionMapper = $transactionMapper;
    }

    public function toDto(Category $category): CategoryDTO
    {
        $dto = new CategoryDTO();
        $this->mapCommonFields($dto, $category);
        return $dto;
    }

    public function toDtoWithTransactions(Category $category, array $transactions): CategoryWithTransactionsDTO
    {
        $dto = new CategoryWithTransactionsDTO();
        $this->mapCommonFields($dto, $category);

        $dto->transactions = array_map(
            fn($transaction) => $this->transactionMapper->toDto($transaction),
            $transactions
        );

        return $dto;
    }

    /**
     * Map gemeenschappelijke velden van Category naar DTO
     */
    private function mapCommonFields(CategoryDTO $dto, Category $category): void
    {
        $dto->id = $category->getId();
        $dto->name = $category->getName();
        $dto->icon = $category->getIcon();
        $dto->color = $category->getColor();
        $dto->createdAt = $category->getCreatedAt();
        $dto->updatedAt = $category->getUpdatedAt();
        $dto->accountId = $category->getAccount()->getId();

        // Budget information
        $budget = $category->getBudget();
        $dto->budgetId = $budget?->getId();
        $dto->budgetName = $budget?->getName();
    }
}