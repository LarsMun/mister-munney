<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AssignCategoriesDTO
{
    #[Assert\NotBlank(message: 'Category IDs zijn verplicht')]
    #[Assert\Type(type: 'array', message: 'Category IDs moeten een array zijn')]
    #[Assert\All([
        new Assert\Type(type: 'integer', message: 'Elke category ID moet een integer zijn'),
        new Assert\Positive(message: 'Category ID moet positief zijn')
    ])]
    public array $categoryIds = [];
}