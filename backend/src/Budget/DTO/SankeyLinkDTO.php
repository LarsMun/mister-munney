<?php

declare(strict_types=1);

namespace App\Budget\DTO;

/**
 * Represents a link (flow) between two nodes in the Sankey diagram
 */
class SankeyLinkDTO
{
    /**
     * Index into the nodes array (source node)
     */
    public int $source;

    /**
     * Index into the nodes array (target node)
     */
    public int $target;

    /**
     * Amount in euros (always positive)
     */
    public float $value;
}
