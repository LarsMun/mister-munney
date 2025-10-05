<?php

namespace App\Transaction\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'DTO voor het koppelen van een categorie aan een transactie')]
class SetCategoryDTO
{
    #[Assert\NotNull(message: 'categoryId is verplicht')]
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)]
    public int $categoryId;
}