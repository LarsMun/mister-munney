<?php

namespace App\Account\Mapper;

use App\Account\DTO\AccountDTO;
use App\Entity\Account;

class AccountMapper
{
    public function toSimpleDto(Account $entity): AccountDTO
    {
        $dto = new AccountDTO();
        $dto->id = $entity->getId();
        $dto->name = $entity->getName();
        $dto->accountNumber = $entity->getAccountNumber();
        $dto->isDefault = $entity->isDefault();
        $dto->type = $entity->getType()->value;
        $dto->parentAccountId = $entity->getParentAccount()?->getId();

        return $dto;
    }
}