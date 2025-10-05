<?php

namespace App\Transaction\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class TransactionFilterDTO
{
    public ?int $accountId = null;
    public ?string $search = null; // zoekt in description, counterparty_account, notes

    #[Assert\Date(message: "startDate moet een geldige datum zijn in YYYY-MM-DD formaat.")]
    public ?string $startDate = null;

    #[Assert\Date(message: "endDate moet een geldige datum zijn in YYYY-MM-DD formaat.")]
    public ?string $endDate = null;
    public ?float $minAmount = null;
    public ?float $maxAmount = null;
    public ?string $transactionType = null; // 'credit' of 'debit'
    public ?string $sortBy = 'date';
    public ?string $sortDirection = 'DESC';
}