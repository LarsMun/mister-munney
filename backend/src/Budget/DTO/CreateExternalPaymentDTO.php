<?php

namespace App\Budget\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateExternalPaymentDTO
{
    #[Assert\NotBlank(message: 'Amount is required')]
    #[Assert\Positive(message: 'Amount must be positive')]
    public float $amount;

    #[Assert\NotBlank(message: 'Date is required')]
    #[Assert\Date(message: 'Invalid date format')]
    public string $paidOn;

    #[Assert\NotBlank(message: 'Payer source is required')]
    #[Assert\Choice(choices: ['SELF', 'MORTGAGE_DEPOT', 'INSURER', 'OTHER'], message: 'Invalid payer source')]
    public string $payerSource;

    #[Assert\NotBlank(message: 'Note is required')]
    #[Assert\Length(min: 1, max: 1000, minMessage: 'Note must be at least 1 character', maxMessage: 'Note cannot exceed 1000 characters')]
    public string $note;
}
