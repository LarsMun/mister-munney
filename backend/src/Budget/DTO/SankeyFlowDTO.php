<?php

declare(strict_types=1);

namespace App\Budget\DTO;

/**
 * Complete Sankey flow data for rendering the diagram
 */
class SankeyFlowDTO
{
    /**
     * @var SankeyNodeDTO[]
     */
    public array $nodes = [];

    /**
     * @var SankeyLinkDTO[]
     */
    public array $links = [];

    /**
     * Data mode: 'actual' | 'median'
     */
    public string $mode;

    /**
     * Total income amount
     */
    public float $totalIncome;

    /**
     * Total expense amount
     */
    public float $totalExpense;

    /**
     * Net flow (income - expense)
     */
    public float $netFlow;
}
