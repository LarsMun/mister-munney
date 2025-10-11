<?php

namespace App\SavingsAccount\DTO;

use App\Account\DTO\AccountDTO;

class SavingsAccountDTO
{
    public int $id;
    public string $name;
    public ?float $targetAmount = null;
    public int $accountId;
    public ?string $color;
    
    /** @var AccountDTO|null */
    public ?AccountDTO $account = null;
    
    /** @var array */
    public array $patterns = [];
}