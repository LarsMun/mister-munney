<?php

namespace App\Pattern\DTO;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Schema(schema: 'AssignPatternDateRangeDTO')]
#[Assert\Expression(
    "this.startDate <= this.endDate",
    message: "De startdatum moet vóór of gelijk zijn aan de einddatum."
)]
class AssignPatternDateRangeDTO
{
    #[Assert\NotBlank]
    #[Assert\Date]
    #[OA\Property(
        description: 'Startdatum van de periode waarin transacties opnieuw worden geëvalueerd',
        type: 'string',
        format: 'date',
        example: '2024-01-01'
    )]
    public string $startDate;

    #[Assert\NotBlank]
    #[Assert\Date]
    #[OA\Property(
        description: 'Einddatum van de periode waarin transacties opnieuw worden geëvalueerd',
        type: 'string',
        format: 'date',
        example: '2024-01-31'
    )]
    public string $endDate;


}