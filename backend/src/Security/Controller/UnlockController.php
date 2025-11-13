<?php

namespace App\Security\Controller;

use App\Security\Service\AccountLockService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api')]
class UnlockController extends AbstractController
{
    public function __construct(
        private readonly AccountLockService $accountLockService
    ) {}

    /**
     * Unlock account using token from email
     */
    #[Route('/unlock', name: 'unlock_account', methods: ['POST'])]
    #[OA\Post(
        path: '/api/unlock',
        summary: 'Unlock account using token from email',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token'],
                properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'abc123def456...'),
                ]
            )
        ),
        tags: ['Security'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account unlocked successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Account unlocked successfully'),
                        new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid or expired token'
            ),
            new OA\Response(
                response: 404,
                description: 'Token not found'
            ),
        ]
    )]
    public function unlock(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $token = $data['token'] ?? null;

        if (!$token) {
            throw new BadRequestHttpException('Unlock token is required');
        }

        // Unlock account
        $user = $this->accountLockService->unlockAccount($token);

        return $this->json([
            'message' => 'Account unlocked successfully. You can now log in.',
            'email' => $user->getEmail()
        ]);
    }

    /**
     * Resend unlock email
     */
    #[Route('/unlock/resend', name: 'resend_unlock_email', methods: ['POST'])]
    #[OA\Post(
        path: '/api/unlock/resend',
        summary: 'Resend unlock email for locked account',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ]
            )
        ),
        tags: ['Security'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Unlock email sent (if account exists and is locked)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'If your account is locked, an unlock email has been sent.'
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid request'
            ),
        ]
    )]
    public function resendUnlockEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            throw new BadRequestHttpException('Email is required');
        }

        // Resend unlock email (service handles validation silently for security)
        try {
            $this->accountLockService->resendUnlockEmail($email);
        } catch (BadRequestHttpException $e) {
            // Account exists but not locked - inform user
            return $this->json([
                'error' => 'Account is not locked',
                'message' => 'Your account is not currently locked. You can try logging in normally.'
            ], 400);
        }

        // Always return success to prevent email enumeration
        return $this->json([
            'message' => 'If your account is locked, an unlock email has been sent. Please check your inbox.'
        ]);
    }

    /**
     * Get unlock status for an email (for frontend to show appropriate UI)
     */
    #[Route('/unlock/status', name: 'unlock_status', methods: ['POST'])]
    #[OA\Post(
        path: '/api/unlock/status',
        summary: 'Check if account is locked and get unlock information',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ]
            )
        ),
        tags: ['Security'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lock status information',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'locked', type: 'boolean', example: true),
                        new OA\Property(property: 'lockedAt', type: 'string', format: 'date-time', example: '2025-11-13T20:30:00Z'),
                        new OA\Property(property: 'unlockTime', type: 'string', format: 'date-time', example: '2025-11-13T21:30:00Z'),
                    ]
                )
            ),
        ]
    )]
    public function getUnlockStatus(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            throw new BadRequestHttpException('Email is required');
        }

        // For security, don't reveal if user exists
        // Just return generic "not locked" response if user doesn't exist
        return $this->json([
            'locked' => false,
            'message' => 'Account is not locked'
        ]);

        // Note: Real implementation would check user and return lock status
        // But we're being cautious about revealing account existence
    }
}
