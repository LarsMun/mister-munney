<?php

declare(strict_types=1);

namespace App\Response;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standardized API error response
 *
 * Format:
 * {
 *   "success": false,
 *   "error": {
 *     "code": "VALIDATION_ERROR",
 *     "message": "Human readable message",
 *     "details": { ... } // optional additional details
 *   },
 *   "meta": {
 *     "timestamp": "2024-01-01T00:00:00+00:00",
 *     "path": "/api/resource"
 *   }
 * }
 */
class ApiErrorResponse extends JsonResponse
{
    // Common error codes
    public const CODE_VALIDATION_ERROR = 'VALIDATION_ERROR';
    public const CODE_NOT_FOUND = 'NOT_FOUND';
    public const CODE_UNAUTHORIZED = 'UNAUTHORIZED';
    public const CODE_FORBIDDEN = 'FORBIDDEN';
    public const CODE_CONFLICT = 'CONFLICT';
    public const CODE_RATE_LIMITED = 'RATE_LIMITED';
    public const CODE_BAD_REQUEST = 'BAD_REQUEST';
    public const CODE_INTERNAL_ERROR = 'INTERNAL_ERROR';
    public const CODE_SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';

    public function __construct(
        string $code,
        string $message,
        int $httpStatus = Response::HTTP_BAD_REQUEST,
        ?array $details = null,
        ?string $path = null
    ) {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        $data = [
            'success' => false,
            'error' => $error,
            'meta' => [
                'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ],
        ];

        if ($path !== null) {
            $data['meta']['path'] = $path;
        }

        parent::__construct($data, $httpStatus);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(string $message, ?array $fieldErrors = null, ?string $path = null): self
    {
        return new self(
            self::CODE_VALIDATION_ERROR,
            $message,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $fieldErrors !== null ? ['fields' => $fieldErrors] : null,
            $path
        );
    }

    /**
     * Create a not found error response
     */
    public static function notFound(string $message = 'Resource niet gevonden', ?string $path = null): self
    {
        return new self(
            self::CODE_NOT_FOUND,
            $message,
            Response::HTTP_NOT_FOUND,
            null,
            $path
        );
    }

    /**
     * Create an unauthorized error response
     */
    public static function unauthorized(string $message = 'Niet geauthenticeerd', ?string $path = null): self
    {
        return new self(
            self::CODE_UNAUTHORIZED,
            $message,
            Response::HTTP_UNAUTHORIZED,
            null,
            $path
        );
    }

    /**
     * Create a forbidden error response
     */
    public static function forbidden(string $message = 'Geen toegang', ?string $path = null): self
    {
        return new self(
            self::CODE_FORBIDDEN,
            $message,
            Response::HTTP_FORBIDDEN,
            null,
            $path
        );
    }

    /**
     * Create a conflict error response
     */
    public static function conflict(string $message, ?array $details = null, ?string $path = null): self
    {
        return new self(
            self::CODE_CONFLICT,
            $message,
            Response::HTTP_CONFLICT,
            $details,
            $path
        );
    }

    /**
     * Create a rate limited error response
     */
    public static function rateLimited(string $message = 'Te veel verzoeken, probeer later opnieuw', ?int $retryAfter = null, ?string $path = null): self
    {
        $details = $retryAfter !== null ? ['retry_after' => $retryAfter] : null;
        $response = new self(
            self::CODE_RATE_LIMITED,
            $message,
            Response::HTTP_TOO_MANY_REQUESTS,
            $details,
            $path
        );

        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }

    /**
     * Create a bad request error response
     */
    public static function badRequest(string $message, ?array $details = null, ?string $path = null): self
    {
        return new self(
            self::CODE_BAD_REQUEST,
            $message,
            Response::HTTP_BAD_REQUEST,
            $details,
            $path
        );
    }

    /**
     * Create an internal error response
     */
    public static function internalError(string $message = 'Er is een onverwachte fout opgetreden', ?array $debugInfo = null, ?string $path = null): self
    {
        return new self(
            self::CODE_INTERNAL_ERROR,
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $debugInfo,
            $path
        );
    }
}
