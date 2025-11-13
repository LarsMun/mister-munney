<?php

namespace App\Account\Controller;

use App\Account\Service\AccountService;
use App\Account\Service\AccountSharingService;
use App\User\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Account Sharing',
    description: 'Manage account sharing and invitations'
)]
#[Route('/api/accounts', name: 'api_account_sharing_')]
class AccountSharingController extends AbstractController
{
    public function __construct(
        private readonly AccountService $accountService,
        private readonly AccountSharingService $accountSharingService,
        private readonly UserRepository $userRepository
    ) {
    }

    /**
     * Share account with another user via email
     */
    #[OA\Post(
        path: '/api/accounts/{id}/share',
        summary: 'Share account with another user',
        tags: ['Account Sharing'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Account ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'partner@example.com')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invitation sent (returns success even if email not found for security)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Invitation sent successfully.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Bad request (user already has access)'),
            new OA\Response(response: 403, description: 'Forbidden (not the owner)'),
            new OA\Response(response: 404, description: 'Account not found')
        ]
    )]
    #[Route('/{id}/share', name: 'share', methods: ['POST'])]
    public function shareAccount(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json(['error' => 'Email is required'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format'], 400);
        }

        try {
            $account = $this->accountService->getById($id);

            // shareAccount returns null if user not found (anti-enumeration)
            // We return success anyway for security
            $this->accountSharingService->shareAccount($account, $user, $email);

            return $this->json([
                'message' => 'Invitation sent successfully.'
            ]);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to share account'], 500);
        }
    }

    /**
     * Get all pending invitations for current user
     */
    #[OA\Get(
        path: '/api/accounts/invitations',
        summary: 'Get pending invitations for current user',
        tags: ['Account Sharing'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of pending invitations',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'accountId', type: 'integer', example: 5),
                            new OA\Property(property: 'accountNumber', type: 'string', example: 'NL91ABNA0417164300'),
                            new OA\Property(property: 'accountName', type: 'string', example: 'Joint Account'),
                            new OA\Property(property: 'invitedBy', type: 'string', example: 'partner@example.com'),
                            new OA\Property(property: 'invitedAt', type: 'string', format: 'date-time', example: '2025-01-01T12:00:00Z'),
                            new OA\Property(property: 'expiresAt', type: 'string', format: 'date-time', example: '2025-01-08T12:00:00Z')
                        ]
                    )
                )
            )
        ]
    )]
    #[Route('/invitations', name: 'invitations', methods: ['GET'])]
    public function getInvitations(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $invitations = $this->accountSharingService->getPendingInvitationsForUser($user);

        $data = array_map(function ($accountUser) {
            $account = $accountUser->getAccount();
            $invitedBy = $accountUser->getInvitedBy();

            return [
                'id' => $accountUser->getId(),
                'accountId' => $account->getId(),
                'accountNumber' => $account->getAccountNumber(),
                'accountName' => $account->getName(),
                'invitedBy' => $invitedBy ? $invitedBy->getEmail() : null,
                'invitedAt' => $accountUser->getInvitedAt()?->format('c'),
                'expiresAt' => $accountUser->getExpiresAt()?->format('c')
            ];
        }, $invitations);

        return $this->json($data);
    }

    /**
     * Accept a pending invitation
     */
    #[OA\Post(
        path: '/api/accounts/invitations/{id}/accept',
        summary: 'Accept a pending invitation',
        tags: ['Account Sharing'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Invitation ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invitation accepted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Invitation accepted successfully.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Bad request (not pending or expired)'),
            new OA\Response(response: 403, description: 'Forbidden (not your invitation)'),
            new OA\Response(response: 404, description: 'Invitation not found')
        ]
    )]
    #[Route('/invitations/{id}/accept', name: 'accept_invitation', methods: ['POST'])]
    public function acceptInvitation(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $accountUser = $this->accountSharingService->getPendingInvitationById($id);

            if (!$accountUser) {
                return $this->json(['error' => 'Invitation not found'], 404);
            }

            $this->accountSharingService->acceptInvitation($accountUser, $user);

            return $this->json([
                'message' => 'Invitation accepted successfully.'
            ]);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to accept invitation'], 500);
        }
    }

    /**
     * Revoke user access to account
     */
    #[OA\Delete(
        path: '/api/accounts/{id}/users/{userId}',
        summary: 'Revoke user access to account',
        tags: ['Account Sharing'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Account ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'userId',
                description: 'User ID to revoke',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Access revoked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Access revoked successfully.')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Bad request (cannot revoke owner)'),
            new OA\Response(response: 403, description: 'Forbidden (not the owner)'),
            new OA\Response(response: 404, description: 'Account or user not found')
        ]
    )]
    #[Route('/{id}/users/{userId}', name: 'revoke_access', methods: ['DELETE'])]
    public function revokeAccess(int $id, int $userId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $account = $this->accountService->getById($id);
            $userToRevoke = $this->userRepository->find($userId);

            if (!$userToRevoke) {
                return $this->json(['error' => 'User not found'], 404);
            }

            $this->accountSharingService->revokeAccess($account, $user, $userToRevoke);

            return $this->json([
                'message' => 'Access revoked successfully.'
            ]);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to revoke access'], 500);
        }
    }

    /**
     * Get all users with access to an account
     */
    #[OA\Get(
        path: '/api/accounts/{id}/users',
        summary: 'Get all users with access to account',
        tags: ['Account Sharing'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Account ID',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of users with access',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'userId', type: 'integer', example: 1),
                            new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                            new OA\Property(property: 'role', type: 'string', enum: ['owner', 'shared'], example: 'owner'),
                            new OA\Property(property: 'status', type: 'string', enum: ['active', 'pending', 'revoked'], example: 'active')
                        ]
                    )
                )
            ),
            new OA\Response(response: 403, description: 'Forbidden (no access to account)'),
            new OA\Response(response: 404, description: 'Account not found')
        ]
    )]
    #[Route('/{id}/users', name: 'get_users', methods: ['GET'])]
    public function getAccountUsers(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        try {
            $account = $this->accountService->getById($id);

            // Verify user has access to this account
            if (!$account->hasAccess($user)) {
                return $this->json(['error' => 'Access denied'], 403);
            }

            $accountUsers = $this->accountSharingService->getAccountUsers($account);

            $data = array_map(function ($accountUser) {
                $accountUserEntity = $accountUser->getUser();

                return [
                    'userId' => $accountUserEntity->getId(),
                    'email' => $accountUserEntity->getEmail(),
                    'role' => $accountUser->getRole()->value,
                    'status' => $accountUser->getStatus()->value
                ];
            }, $accountUsers);

            return $this->json($data);

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to get users'], 500);
        }
    }
}
