<?php

declare(strict_types=1);

namespace App\Budget\DTO;

/**
 * Represents a node in the Sankey diagram
 */
class SankeyNodeDTO
{
    public string $name;

    /**
     * Type of node: 'income_budget' | 'income_category' | 'total' | 'expense_budget' | 'expense_category'
     */
    public string $type;

    /**
     * Budget or category ID (null for 'total' node)
     */
    public ?int $id = null;

    /**
     * Category color (only for category nodes)
     */
    public ?string $color = null;
}
