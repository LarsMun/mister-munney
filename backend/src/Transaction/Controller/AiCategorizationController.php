<?php

namespace App\Transaction\Controller;

use App\Account\Repository\AccountRepository;
use App\Transaction\Repository\TransactionRepository;
use App\Transaction\Service\AiCategorizationService;
use App\Transaction\Service\TransactionService;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/account/{accountId}/transactions')]
class AiCategorizationController extends AbstractController
{
    public function __construct(
        private readonly AiCategorizationService $aiCategorizationService,
        private readonly TransactionRepository $transactionRepository,
        private readonly TransactionService $transactionService,
        private readonly LoggerInterface $logger,
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * Verify that the authenticated user owns the specified account
     */
    private function verifyAccountOwnership(int $accountId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    #[Route('/ai-suggest-categories', name: 'api_transactions_ai_suggest_categories', methods: ['POST'])]
    #[OA\Post(
        summary: 'Get AI-powered category suggestions for uncategorized transactions',
        tags: ['Transactions']
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'limit', type: 'integer', example: 50, description: 'Maximum number of transactions to suggest categories for')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Category suggestions for transactions',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'suggestions',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'transactionId', type: 'integer'),
                            new OA\Property(property: 'suggestedCategoryId', type: 'integer', nullable: true),
                            new OA\Property(property: 'confidence', type: 'number', format: 'float'),
                            new OA\Property(property: 'reasoning', type: 'string')
                        ]
                    )
                )
            ]
        )
    )]
    public function suggestCategories(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            $data = json_decode($request->getContent(), true);
            $limit = $data['limit'] ?? 50;

            // Get uncategorized transactions
            $transactions = $this->transactionRepository->findUncategorizedTransactions($accountId, $limit);

            if (empty($transactions)) {
                return new JsonResponse([
                    'suggestions' => [],
                    'message' => 'No uncategorized transactions found'
                ]);
            }

            $suggestions = $this->aiCategorizationService->suggestCategories($transactions, $accountId);

            return new JsonResponse([
                'suggestions' => $suggestions,
                'total' => count($suggestions)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AI category suggestion failed', [
                'accountId' => $accountId,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Failed to generate AI suggestions: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/bulk-assign-categories', name: 'api_transactions_bulk_assign_categories', methods: ['POST'])]
    #[OA\Post(
        summary: 'Bulk assign categories to multiple transactions',
        tags: ['Transactions']
    )]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'assignments',
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'transactionId', type: 'integer'),
                            new OA\Property(property: 'categoryId', type: 'integer')
                        ]
                    ),
                    example: [
                        ['transactionId' => 123, 'categoryId' => 5],
                        ['transactionId' => 124, 'categoryId' => 5]
                    ]
                )
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Categories assigned successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'integer', description: 'Number of successfully assigned categories'),
                new OA\Property(property: 'failed', type: 'integer', description: 'Number of failed assignments'),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
            ]
        )
    )]
    public function bulkAssignCategories(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            $data = json_decode($request->getContent(), true);
            $assignments = $data['assignments'] ?? [];

            if (empty($assignments)) {
                return new JsonResponse([
                    'error' => 'No assignments provided'
                ], Response::HTTP_BAD_REQUEST);
            }

            $success = 0;
            $failed = 0;
            $errors = [];

            foreach ($assignments as $assignment) {
                $transactionId = $assignment['transactionId'] ?? null;
                $categoryId = $assignment['categoryId'] ?? null;

                if ($transactionId === null || $categoryId === null) {
                    $failed++;
                    $errors[] = "Invalid assignment data: transactionId or categoryId missing";
                    continue;
                }

                try {
                    $this->transactionService->setCategory($transactionId, $categoryId);
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    $errors[] = "Transaction {$transactionId}: {$e->getMessage()}";
                    $this->logger->warning('Bulk assign category failed for transaction', [
                        'transactionId' => $transactionId,
                        'categoryId' => $categoryId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return new JsonResponse([
                'success' => $success,
                'failed' => $failed,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Bulk assign categories failed', [
                'accountId' => $accountId,
                'error' => $e->getMessage()
            ]);

            return new JsonResponse([
                'error' => 'Failed to assign categories: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
