<?php

namespace App\Transaction\Request;

use Symfony\Component\Validator\Constraints as Assert;

class TransactionFilterRequest
{
    #[Assert\NotNull]
    public int $accountId;

    #[Assert\Optional]
    public ?string $search = null;

    #[Assert\Optional]
    public ?string $transactionType = null;

    #[Assert\Optional]
    #[Assert\Regex('/^\d{4}-\d{2}-\d{2}$/')]
    public ?string $startDate = null;

    #[Assert\Optional]
    #[Assert\Regex('/^\d{4}-\d{2}-\d{2}$/')]
    public ?string $endDate = null;

    #[Assert\Optional]
    public ?float $minAmount = null;

    #[Assert\Optional]
    public ?float $maxAmount = null;

    #[Assert\Optional]
    public ?string $sortBy = null;

    #[Assert\Optional]
    public ?string $sortDirection = null;
}
