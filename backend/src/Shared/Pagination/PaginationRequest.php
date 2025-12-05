<?php

namespace App\Shared\Pagination;

use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Standard pagination parameters for API requests.
 */
#[OA\Schema(schema: 'PaginationRequest')]
class PaginationRequest
{
    public const DEFAULT_PAGE = 1;
    public const DEFAULT_LIMIT = 50;
    public const MAX_LIMIT = 200;

    #[Assert\Positive(message: 'Paginanummer moet positief zijn')]
    #[OA\Property(description: 'Paginanummer (1-based)', type: 'integer', minimum: 1, example: 1)]
    public int $page = self::DEFAULT_PAGE;

    #[Assert\Positive(message: 'Limiet moet positief zijn')]
    #[Assert\LessThanOrEqual(value: self::MAX_LIMIT, message: 'Limiet mag maximaal {{ compared_value }} zijn')]
    #[OA\Property(description: 'Aantal items per pagina', type: 'integer', minimum: 1, maximum: self::MAX_LIMIT, example: 50)]
    public int $limit = self::DEFAULT_LIMIT;

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public static function fromRequest(\Symfony\Component\HttpFoundation\Request $request): self
    {
        $pagination = new self();
        $pagination->page = max(1, (int) $request->query->get('page', self::DEFAULT_PAGE));
        $pagination->limit = min(
            self::MAX_LIMIT,
            max(1, (int) $request->query->get('limit', self::DEFAULT_LIMIT))
        );
        return $pagination;
    }
}
