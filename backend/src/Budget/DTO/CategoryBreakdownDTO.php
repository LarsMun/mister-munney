<?php

namespace App\Budget\DTO;

class CategoryBreakdownDTO
{
    public int $categoryId;
    public string $categoryName;
    public string $categoryColor;
    public float $spentAmount;
    public int $transactionCount;
}
