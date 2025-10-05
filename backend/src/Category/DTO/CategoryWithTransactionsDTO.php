<?php

namespace App\Category\DTO;

use App\Transaction\DTO\TransactionDTO;
use DateTimeImmutable;

class CategoryWithTransactionsDTO extends CategoryDTO
{
    /** @var TransactionDTO[] */
    public array $transactions = [];
}