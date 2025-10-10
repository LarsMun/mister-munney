<?php

namespace App\SavingsAccount\Mapper;

use App\Entity\Account;
use App\Entity\SavingsAccount;
use App\Mapper\PayloadMapper;
use App\SavingsAccount\DTO\CreateSavingsAccountDTO;
use App\SavingsAccount\DTO\SavingsAccountDTO;
use App\Account\Mapper\AccountMapper;
use App\SavingsAccount\DTO\UpdateSavingsAccountDTO;

class SavingsAccountMapper
{
    private AccountMapper $accountMapper;
    private PayloadMapper $payloadMapper;

    public function __construct(
        AccountMapper $accountMapper,
        PayloadMapper $payloadMapper
    ) {
        $this->accountMapper = $accountMapper;
        $this->payloadMapper = $payloadMapper;
    }

    public function toSimpleDto(SavingsAccount $entity): SavingsAccountDTO
    {
        $dto = new SavingsAccountDTO();
        $dto->id = $entity->getId();
        $dto->name = $entity->getName();
        $dto->color = $entity->getColor();

        $dto->targetAmount = $entity->getTargetAmount() !== null
            ? (float)$entity->getTargetAmount()
            : null;

        $dto->accountId = $entity->getAccount()->getId();

        return $dto;
    }

    public function toDtoList(array $entities): array
    {
        return array_map(
            fn(SavingsAccount $entity) => $this->toSimpleDto($entity),
            $entities
        );
    }

    public function fromCreateDto(CreateSavingsAccountDTO $dto, Account $account): SavingsAccount
    {
        $entity = new SavingsAccount();
        $entity->setName($dto->name);
        $entity->setAccount($account);
        $entity->setColor($dto->color);

        if ($dto->targetAmount !== null) {
            $entity->setTargetAmount(number_format($dto->targetAmount, 2, '.', ''));
        }

        return $entity;
    }

    public function fromUpdatePayload(array $payload): UpdateSavingsAccountDTO
    {
        return $this->payloadMapper->map($payload, new UpdateSavingsAccountDTO(), strict: true);
    }

    public function toWithPatternsAndAccountDto(SavingsAccount $entity): SavingsAccountDTO
    {
        $dto = $this->toSimpleDto($entity);
        
        // Add account information
        $dto->account = $this->accountMapper->toSimpleDto($entity->getAccount());
        
        // Add patterns (empty array for now, can be expanded later)
        $dto->patterns = [];
        
        return $dto;
    }

    public function toDetailedDtoList(array $entities): array
    {
        return array_map(
            fn(SavingsAccount $entity) => $this->toWithPatternsAndAccountDto($entity),
            $entities
        );
    }
}