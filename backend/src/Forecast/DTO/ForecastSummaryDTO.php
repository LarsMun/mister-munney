<?php

namespace App\Forecast\DTO;

class ForecastSummaryDTO
{
    public string $month; // YYYY-MM format

    /** @var ForecastItemDTO[] */
    public array $incomeItems = [];

    /** @var ForecastItemDTO[] */
    public array $expenseItems = [];

    public float $totalExpectedIncome = 0;
    public float $totalActualIncome = 0;

    public float $totalExpectedExpenses = 0;
    public float $totalActualExpenses = 0;

    public float $expectedResult = 0; // income - expenses
    public float $actualResult = 0;

    public float $currentBalance = 0; // Huidig saldo
    public float $projectedBalance = 0; // Saldo na verwachte in/uitgaven
}
