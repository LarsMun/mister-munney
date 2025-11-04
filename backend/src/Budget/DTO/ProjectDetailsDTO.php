<?php

namespace App\Budget\DTO;

class ProjectDetailsDTO
{
    public int $id;
    public string $name;
    public ?string $description = null;
    public int $durationMonths;
    public ?string $status = null;
    public array $totals; // From ProjectAggregatorService
    public array $timeSeries; // Monthly bars + cumulative line
    public int $categoryCount;
}
