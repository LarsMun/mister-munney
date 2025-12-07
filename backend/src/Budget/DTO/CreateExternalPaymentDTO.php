<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateExternalPaymentDTO
{
    #[Assert\NotBlank(message: 'Bedrag is verplicht')]
    #[Assert\Positive(message: 'Bedrag moet een positief getal zijn')]
    #[Assert\LessThan(value: 10000000, message: 'Bedrag mag maximaal 10.000.000 zijn')]
    public float $amount;

    #[Assert\NotBlank(message: 'Datum is verplicht')]
    #[Assert\Date(message: 'Ongeldige datumformaat (gebruik YYYY-MM-DD)')]
    public string $paidOn;

    #[Assert\NotBlank(message: 'Betaalbron is verplicht')]
    #[Assert\Choice(
        choices: ['SELF', 'MORTGAGE_DEPOT', 'INSURER', 'OTHER'],
        message: 'Ongeldige betaalbron. Kies uit: SELF, MORTGAGE_DEPOT, INSURER, OTHER'
    )]
    public string $payerSource;

    #[Assert\NotBlank(message: 'Notitie is verplicht')]
    #[Assert\Length(
        min: 1,
        max: 1000,
        minMessage: 'Notitie moet minimaal 1 karakter zijn',
        maxMessage: 'Notitie mag maximaal 1000 karakters zijn'
    )]
    public string $note;
}
