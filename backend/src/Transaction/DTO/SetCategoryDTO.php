<?php

namespace App\Transaction\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'DTO voor het koppelen van een categorie aan een transactie')]
class SetCategoryDTO
{
    #[Assert\NotNull(message: 'Categorie ID is verplicht')]
    #[Assert\Positive(message: 'Categorie ID moet een positief getal zijn')]
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)]
    public int $categoryId;
}