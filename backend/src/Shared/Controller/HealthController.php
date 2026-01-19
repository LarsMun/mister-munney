<?php

namespace App\Shared\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api')]
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $projectDir
    ) {}

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    #[OA\Get(
        path: '/api/health',
        summary: 'Health check endpoint for monitoring and deployment verification',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'System is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'healthy'),
                        new OA\Property(
                            property: 'checks',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'database', type: 'string', example: 'ok'),
                                new OA\Property(property: 'jwt_keys', type: 'string', example: 'ok'),
                            ]
                        ),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'System is unhealthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'unhealthy'),
                        new OA\Property(property: 'checks', type: 'object'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time'),
                    ]
                )
            ),
        ]
    )]
    public function health(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Check database connection
        try {
            $this->connection->executeQuery('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error: ' . $e->getMessage();
            $healthy = false;
        }

        // Check JWT keys exist
        $privateKeyPath = $this->projectDir . '/config/jwt/private.pem';
        $publicKeyPath = $this->projectDir . '/config/jwt/public.pem';

        if (file_exists($privateKeyPath) && file_exists($publicKeyPath)) {
            $checks['jwt_keys'] = 'ok';
        } else {
            $checks['jwt_keys'] = 'error: JWT keys not found';
            $healthy = false;
        }

        $response = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
            'timestamp' => (new \DateTime())->format(\DateTime::ATOM),
        ];

        return new JsonResponse($response, $healthy ? 200 : 503);
    }

    #[Route('/health/live', name: 'health_liveness', methods: ['GET'])]
    #[OA\Get(
        path: '/api/health/live',
        summary: 'Liveness probe - returns 200 if the application is running',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application is alive',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'alive'),
                    ]
                )
            ),
        ]
    )]
    public function liveness(): JsonResponse
    {
        return new JsonResponse(['status' => 'alive']);
    }

    #[Route('/health/ready', name: 'health_readiness', methods: ['GET'])]
    #[OA\Get(
        path: '/api/health/ready',
        summary: 'Readiness probe - returns 200 if the application is ready to serve traffic',
        tags: ['System'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Application is ready',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ready'),
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: 'Application is not ready'
            ),
        ]
    )]
    public function readiness(): JsonResponse
    {
        // Check if database is accessible
        try {
            $this->connection->executeQuery('SELECT 1');
            return new JsonResponse(['status' => 'ready']);
        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'not_ready', 'reason' => 'database'], 503);
        }
    }
}
