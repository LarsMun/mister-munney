<?php

namespace App\Transaction\DTO;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class TransactionFilterDTO
{
    #[Assert\Positive(message: 'Account ID moet een positief getal zijn')]
    public ?int $accountId = null;

    #[Assert\Length(max: 255, maxMessage: 'Zoekterm mag maximaal 255 karakters zijn')]
    public ?string $search = null; // zoekt in description, counterparty_account, notes

    #[Assert\Date(message: 'Startdatum moet een geldige datum zijn (YYYY-MM-DD)')]
    public ?string $startDate = null;

    #[Assert\Date(message: 'Einddatum moet een geldige datum zijn (YYYY-MM-DD)')]
    #[Assert\Expression(
        expression: 'this.startDate === null or this.endDate === null or this.startDate <= this.endDate',
        message: 'Einddatum moet gelijk of later zijn dan startdatum'
    )]
    public ?string $endDate = null;

    #[Assert\Type(type: 'numeric', message: 'Minimumbedrag moet een getal zijn')]
    public ?float $minAmount = null;

    #[Assert\Type(type: 'numeric', message: 'Maximumbedrag moet een getal zijn')]
    #[Assert\Expression(
        expression: 'this.minAmount === null or this.maxAmount === null or this.minAmount <= this.maxAmount',
        message: 'Maximumbedrag moet gelijk of groter zijn dan minimumbedrag'
    )]
    public ?float $maxAmount = null;

    #[Assert\Choice(
        choices: ['credit', 'debit', 'CREDIT', 'DEBIT', null],
        message: 'Transactietype moet "credit" of "debit" zijn'
    )]
    public ?string $transactionType = null;

    #[Assert\Choice(
        choices: ['date', 'amount', 'description'],
        message: 'Sorteerveld moet "date", "amount" of "description" zijn'
    )]
    public ?string $sortBy = 'date';

    #[Assert\Choice(
        choices: ['ASC', 'DESC', 'asc', 'desc'],
        message: 'Sorteerrichting moet "ASC" of "DESC" zijn'
    )]
    public ?string $sortDirection = 'DESC';

    public bool $excludeSplitParents = false;
}