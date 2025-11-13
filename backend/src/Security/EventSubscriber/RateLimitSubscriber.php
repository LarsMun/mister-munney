<?php

namespace App\Security\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'limiter.api')]
        private readonly RateLimiterFactory $apiLimiter,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'limiter.auth')]
        private readonly RateLimiterFactory $authLimiter,
        #[\Symfony\Component\DependencyInjection\Attribute\Autowire(service: 'limiter.bulk')]
        private readonly RateLimiterFactory $bulkLimiter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Skip rate limiting for non-API paths
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip rate limiting for health checks and documentation
        if (str_starts_with($path, '/api/doc') || str_starts_with($path, '/api/health')) {
            return;
        }

        // Get client identifier (IP address)
        $clientIp = $request->getClientIp() ?? 'unknown';

        // Choose appropriate rate limiter based on endpoint
        $limiter = match (true) {
            str_starts_with($path, '/api/login'),
            str_starts_with($path, '/api/register') => $this->authLimiter->create($clientIp),

            str_contains($path, '/import'),
            str_contains($path, '/bulk') => $this->bulkLimiter->create($clientIp),

            default => $this->apiLimiter->create($clientIp),
        };

        // Consume one token
        $limit = $limiter->consume(1);

        // Add rate limit headers
        $response = null;
        if (!$limit->isAccepted()) {
            $response = new JsonResponse(
                [
                    'error' => 'Too many requests. Please try again later.',
                    'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                ],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        // Add rate limit info headers to all responses
        if ($response) {
            $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('X-RateLimit-Retry-After', (string) $limit->getRetryAfter()->getTimestamp());
            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $event->setResponse($response);
        }
    }
}
