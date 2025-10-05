<?php

namespace App\SavingsAccount\DTO;

class SavingsAccountDTO
{
    public int $id;
    public string $name;
    public ?string $targetAmount = null;
    public int $accountId;
    public ?string $color;
}