<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
readonly class RateLimitListener
{
    public function __construct(
        private RateLimiterFactory $apiLimiter
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply rate limiting to API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Get client IP address
        $clientIp = $request->getClientIp() ?? 'unknown';

        // Create rate limiter for this IP
        $limiter = $this->apiLimiter->create($clientIp);

        // Consume 1 token from the bucket
        $limit = $limiter->consume(1);

        // Add rate limit headers to response
        $event->getRequest()->attributes->set('rate_limit_info', [
            'limit' => $limit->getLimit(),
            'remaining' => $limit->getRemainingTokens(),
            'reset' => $limit->getRetryAfter()->getTimestamp(),
        ]);

        // If limit exceeded, throw exception
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                $limit->getRetryAfter()->getTimestamp() - time(),
                'API rate limit exceeded. Maximum 100 requests per minute.'
            );
        }
    }
}
