<?php

namespace App\Shared\Pagination;

use OpenApi\Attributes as OA;

/**
 * Generic paginated response wrapper.
 */
#[OA\Schema(schema: 'PaginatedResponse')]
class PaginatedResponse
{
    #[OA\Property(type: 'array', items: new OA\Items(type: 'object'))]
    public array $data;

    #[OA\Property(
        properties: [
            new OA\Property(property: 'page', type: 'integer', example: 1),
            new OA\Property(property: 'limit', type: 'integer', example: 50),
            new OA\Property(property: 'total', type: 'integer', example: 1250),
            new OA\Property(property: 'totalPages', type: 'integer', example: 25),
            new OA\Property(property: 'hasNextPage', type: 'boolean', example: true),
            new OA\Property(property: 'hasPrevPage', type: 'boolean', example: false),
        ],
        type: 'object'
    )]
    public array $pagination;

    public function __construct(
        array $data,
        int $page,
        int $limit,
        int $total
    ) {
        $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 0;

        $this->data = $data;
        $this->pagination = [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'hasNextPage' => $page < $totalPages,
            'hasPrevPage' => $page > 1,
        ];
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'pagination' => $this->pagination,
        ];
    }
}
