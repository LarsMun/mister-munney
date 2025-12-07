<?php

namespace App\EventListener;

use App\Response\ApiErrorResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsEventListener]
readonly class ApiExceptionListener
{
    private KernelInterface $kernel;
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        // Map HTTP status codes to error codes
        $errorCode = $this->mapStatusToErrorCode($statusCode);

        // Build debug info for development
        $debugInfo = null;
        if ($this->kernel->isDebug()) {
            $debugInfo = [
                'exception_type' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice(explode("\n", $exception->getTraceAsString()), 0, 10),
            ];
        }

        $response = new ApiErrorResponse(
            $errorCode,
            $exception->getMessage(),
            $statusCode,
            $debugInfo,
            $path
        );

        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE');

        $event->setResponse($response);
    }

    private function mapStatusToErrorCode(int $statusCode): string
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => ApiErrorResponse::CODE_BAD_REQUEST,
            Response::HTTP_UNAUTHORIZED => ApiErrorResponse::CODE_UNAUTHORIZED,
            Response::HTTP_FORBIDDEN => ApiErrorResponse::CODE_FORBIDDEN,
            Response::HTTP_NOT_FOUND => ApiErrorResponse::CODE_NOT_FOUND,
            Response::HTTP_CONFLICT => ApiErrorResponse::CODE_CONFLICT,
            Response::HTTP_UNPROCESSABLE_ENTITY => ApiErrorResponse::CODE_VALIDATION_ERROR,
            Response::HTTP_TOO_MANY_REQUESTS => ApiErrorResponse::CODE_RATE_LIMITED,
            Response::HTTP_SERVICE_UNAVAILABLE => ApiErrorResponse::CODE_SERVICE_UNAVAILABLE,
            default => ApiErrorResponse::CODE_INTERNAL_ERROR,
        };
    }
}