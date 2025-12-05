<?php

namespace App\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class RequestLoggerSubscriber implements EventSubscriberInterface
{
    private const CORRELATION_ID_HEADER = 'X-Correlation-ID';
    private ?string $correlationId = null;
    private ?float $startTime = null;

    public function __construct(
        private readonly LoggerInterface $requestLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', -100],
            KernelEvents::EXCEPTION => ['onException', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->startTime = microtime(true);

        // Get or generate correlation ID
        $this->correlationId = $request->headers->get(self::CORRELATION_ID_HEADER)
            ?? $this->generateCorrelationId();

        // Store correlation ID in request attributes for later use
        $request->attributes->set('correlation_id', $this->correlationId);

        $this->requestLogger->info('Request started', [
            'correlation_id' => $this->correlationId,
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'client_ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        // Add correlation ID to response headers
        if ($this->correlationId) {
            $response->headers->set(self::CORRELATION_ID_HEADER, $this->correlationId);
        }

        $duration = $this->startTime ? round((microtime(true) - $this->startTime) * 1000, 2) : null;

        $this->requestLogger->info('Request completed', [
            'correlation_id' => $this->correlationId,
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
        ]);
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $this->requestLogger->error('Request failed with exception', [
            'correlation_id' => $this->correlationId,
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
        ]);
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    private function generateCorrelationId(): string
    {
        return sprintf('%s-%s', date('Ymd-His'), bin2hex(random_bytes(4)));
    }
}
