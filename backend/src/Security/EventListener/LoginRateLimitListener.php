<?php

namespace App\Security\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Login-specific rate limiter
 * Limits login attempts to 5 per 5 minutes per IP address
 * Runs independently from the global API rate limiter
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 15)]
readonly class LoginRateLimitListener
{
    public function __construct(
        private RateLimiterFactory $loginLimiter
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only apply to /api/login endpoint
        if ($request->getPathInfo() !== '/api/login') {
            return;
        }

        // Only apply to POST requests (actual login attempts)
        if ($request->getMethod() !== 'POST') {
            return;
        }

        $clientIp = $request->getClientIp() ?? 'unknown';
        $limiter = $this->loginLimiter->create($clientIp);

        // Consume 1 token
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            $minutes = ceil($retryAfter / 60);

            throw new TooManyRequestsHttpException(
                $retryAfter,
                sprintf(
                    'Too many login attempts. Please try again in %d %s.',
                    $minutes,
                    $minutes === 1 ? 'minute' : 'minutes'
                )
            );
        }
    }
}
