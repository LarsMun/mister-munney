<?php

namespace App\Transaction\DTO;

class CreateTemporaryTransactionDTO
{
    public string $date;
    public string $description;
    public float $amount;
    public string $transactionType;
    public ?int $categoryId = null;
}
