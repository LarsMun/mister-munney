<?php

namespace App\Account\DTO;

class AccountDTO
{
    public int $id;
    public ?string $name = null;
    public string $accountNumber;
    public bool $isDefault;
    public string $type;
    public ?int $parentAccountId = null;
}