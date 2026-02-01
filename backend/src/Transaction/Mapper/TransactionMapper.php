<?php

namespace App\Transaction\Mapper;

use App\Category\DTO\CategoryDTO;
use App\Category\Mapper\CategoryMapper;
use App\Entity\Transaction;
use App\Money\MoneyFactory;
use App\Transaction\DTO\TransactionDTO;
use App\Transaction\DTO\TransactionMatchesDTO;

class TransactionMapper
{
    private MoneyFactory $moneyFactory;
    private ?CategoryMapper $categoryMapper = null;

    public function __construct(
        MoneyFactory $moneyFactory
    )
    {
        $this->moneyFactory = $moneyFactory;
    }
    public function toDto(Transaction $transaction): TransactionDTO
    {
        $dto = new TransactionDTO();
        $dto->id = $transaction->getId();
        $dto->hash = $transaction->getHash();
        $dto->date = $transaction->getDate()->format('Y-m-d');
        $dto->description = $transaction->getDescription();
        $dto->accountId = $transaction->getAccountId();
        $dto->counterpartyAccount = $transaction->getCounterpartyAccount();
        $dto->transactionCode = $transaction->getTransactionCode();
        $dto->transactionType = $transaction->getTransactionType()->value;
        $dto->amount = $this->moneyFactory->toFloat($transaction->getAmount());
        $dto->mutationType = $transaction->getMutationType();
        $dto->notes = $transaction->getNotes();
        $dto->balanceAfter = $transaction->getBalanceAfter() !== null
            ? $this->moneyFactory->toFloat($transaction->getBalanceAfter())
            : null;
        $dto->tag = $transaction->getTag();
        $dto->isTemporary = $transaction->isTemporary();

        if ($transaction->getCategory()) {
            $category = $transaction->getCategory();
            $categoryDto = new CategoryDTO();
            $categoryDto->id = $category->getId();
            $categoryDto->name = $category->getName();
            $categoryDto->icon = $category->getIcon();
            $categoryDto->color = $category->getColor();
            $categoryDto->createdAt = $category->getCreatedAt();
            $categoryDto->updatedAt = $category->getUpdatedAt();
            $categoryDto->accountId = $transaction->getAccountId();
            $dto->category = $categoryDto;
        }

        // Add split information
        $dto->hasSplits = $transaction->hasSplits();
        $dto->splitCount = $transaction->getSplits()->count();
        $dto->parentTransactionId = $transaction->getParentTransaction()?->getId();

        return $dto;
    }

    public function toMatchesDto(Transaction $transaction): TransactionMatchesDTO
    {
        $dto = new TransactionMatchesDTO();
        $dto->id = $transaction->getId();
        $dto->hash = $transaction->getHash();
        $dto->date = $transaction->getDate()->format('Y-m-d');
        $dto->description = $transaction->getDescription();
        $dto->accountId = $transaction->getAccount()->getId();
        $dto->counterpartyAccount = $transaction->getCounterpartyAccount();
        $dto->transactionCode = $transaction->getTransactionCode();
        $dto->transactionType = $transaction->getTransactionType()->value;
        $dto->amount = $this->moneyFactory->toFloat($transaction->getAmount());
        $dto->mutationType = $transaction->getMutationType();
        $dto->notes = $transaction->getNotes();
        $dto->balanceAfter = $transaction->getBalanceAfter() !== null
            ? $this->moneyFactory->toFloat($transaction->getBalanceAfter())
            : null;
        $dto->tag = $transaction->getTag();
        $dto->category = $transaction->getCategory() ? $this->categoryMapper->toDto($transaction->getCategory()) : null;
        $dto->matchConflict = false;

        return $dto;
    }

    public function setCategoryMapper(CategoryMapper $categoryMapper): void
    {
        $this->categoryMapper = $categoryMapper;
    }
}