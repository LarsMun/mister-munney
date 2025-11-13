<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
readonly class RateLimitHeaderListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        // Only add headers to API routes
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        // Get rate limit info from request attributes
        $rateLimitInfo = $request->attributes->get('rate_limit_info');

        if ($rateLimitInfo === null) {
            return;
        }

        // Add rate limit headers to response
        $response = $event->getResponse();
        $response->headers->set('X-RateLimit-Limit', (string) $rateLimitInfo['limit']);
        $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitInfo['remaining']);
        $response->headers->set('X-RateLimit-Reset', (string) $rateLimitInfo['reset']);
    }
}
