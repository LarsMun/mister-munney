<?php

namespace App\SavingsAccount\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'AssignPatternDateRangeDTO',
    description: 'DTO voor de daterange van transcaties om automatisch een SavingsAccount aan toe te wijzen.'
)]
class AssignPatternDateRangeDTO
{
    #[Assert\NotBlank(message: 'Startdatum is verplicht')]
    #[Assert\Date(message: 'Moet een geldige datum zijn')]
    #[OA\Property(
        description: 'De startdatum (jjjj-mm-dd)',
        type: 'string',
        example: '2025-01-01'
    )]
    public string $startDate;

    #[Assert\NotBlank(message: 'Einddatum is verplicht')]
    #[Assert\Date(message: 'Moet een geldige datum zijn')]
    #[OA\Property(
        description: 'De einddatum (jjjj-mm-dd)',
        type: 'string',
        example: '2025-01-31'
    )]
    public string $endDate;
}