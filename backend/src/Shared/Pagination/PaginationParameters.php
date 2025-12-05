<?php

namespace App\Shared\Pagination;

use OpenApi\Attributes as OA;

/**
 * Reusable OpenAPI pagination parameter definitions.
 *
 * Usage in controllers:
 * ```php
 * #[OA\Get(
 *     parameters: PaginationParameters::getParameters(),
 *     ...
 * )]
 * ```
 */
class PaginationParameters
{
    public static function getParameters(): array
    {
        return [
            new OA\Parameter(
                name: 'page',
                description: 'Paginanummer (begint bij 1)',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    default: PaginationRequest::DEFAULT_PAGE,
                    example: 1
                )
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Aantal items per pagina',
                in: 'query',
                required: false,
                schema: new OA\Schema(
                    type: 'integer',
                    minimum: 1,
                    maximum: PaginationRequest::MAX_LIMIT,
                    default: PaginationRequest::DEFAULT_LIMIT,
                    example: 50
                )
            ),
        ];
    }
}
