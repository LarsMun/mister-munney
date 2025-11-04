<?php

namespace App\Enum;

enum BudgetType: string
{
    case EXPENSE = 'EXPENSE';
    case INCOME = 'INCOME';
    case PROJECT = 'PROJECT';
}
