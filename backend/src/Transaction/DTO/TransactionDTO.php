<?php

namespace App\Transaction\DTO;

use App\Category\DTO\CategoryDTO;
use App\SavingsAccount\DTO\SavingsAccountDTO;

class TransactionDTO
{
    public int $id;
    public string $hash;
    public string $date;
    public string $description;
    public int $accountId;
    public ?string $counterpartyAccount;
    public ?string $transactionCode;
    public string $transactionType;
    public float $amount;
    public string $mutationType;
    public string $notes;
    public float $balanceAfter;
    public ?string $tag;
    public ?CategoryDTO $category = null;
    public ?SavingsAccountDTO $savingsAccount = null;
    public bool $hasSplits = false;
    public int $splitCount = 0;
    public ?int $parentTransactionId = null;
}