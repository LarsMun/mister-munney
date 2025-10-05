<?php

namespace App\Transaction\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'Payload voor het toewijzen van een spaarrekening aan een transactie')]
class AssignSavingsAccountDTO
{
    #[Assert\NotNull(message: 'savingsAccountId is verplicht')]
    #[Assert\Positive(message: 'savingsAccountId moet positief zijn')]
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 1, example: 2)]
    public int $savingsAccountId;
}