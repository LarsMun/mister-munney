<?php

namespace App\Transaction\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(description: 'DTO voor het koppelen van een categorie aan een transactie')]
class SetCategoryDTO
{
    #[Assert\NotNull(message: 'Categorie ID is verplicht')]
    #[Assert\PositiveOrZero(message: 'Categorie ID moet een positief getal of 0 zijn')]
    #[OA\Property(type: 'integer', maximum: 2147483647, minimum: 0, example: 42, description: 'Categorie ID, of 0 om categorie te verwijderen')]
    public int $categoryId;
}