<?php

namespace App\Transaction\Controller;

use App\Account\Repository\AccountRepository;
use App\Shared\Controller\AccountOwnershipTrait;
use App\Transaction\Service\CreditCardPdfParserService;
use App\Transaction\Service\TransactionSplitService;
use App\Transaction\Repository\TransactionRepository;
use App\Transaction\Mapper\TransactionMapper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/account/{accountId}/transaction')]
#[OA\Tag(name: 'Transaction Splits', description: 'Manage transaction splits for credit card statements')]
class TransactionSplitController extends AbstractController
{
    use AccountOwnershipTrait;

    public function __construct(
        private readonly CreditCardPdfParserService $pdfParser,
        private readonly TransactionSplitService $splitService,
        private readonly TransactionRepository $transactionRepository,
        private readonly TransactionMapper $transactionMapper,
        private readonly AccountRepository $accountRepository
    ) {
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    /**
     * Parse credit card PDF and extract transactions
     */
    #[Route('/{transactionId}/parse-creditcard', name: 'parse_creditcard_pdf', methods: ['POST'])]
    #[OA\Post(
        path: '/api/account/{accountId}/transaction/{transactionId}/parse-creditcard',
        description: 'Parse ING credit card statement text and extract transactions for splitting',
        summary: 'Parse credit card statement',
        requestBody: new OA\RequestBody(
            description: 'Credit card statement text (extracted from PDF)',
            required: true,
            content: new OA\JsonContent(
                required: ['pdfText'],
                properties: [
                    new OA\Property(property: 'pdfText', type: 'string', description: 'Full text content extracted from credit card PDF')
                ]
            )
        ),
        tags: ['Transaction Splits'],
        parameters: [
            new OA\Parameter(name: 'accountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'transactionId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Parsed transactions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'total', type: 'number'),
                        new OA\Property(property: 'parentAmount', type: 'number'),
                        new OA\Property(property: 'valid', type: 'boolean')
                    ]
                )
            )
        ]
    )]
    public function parseCreditCardPdf(int $accountId, int $transactionId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        $pdfText = $data['pdfText'] ?? '';

        if (empty($pdfText)) {
            return $this->json(['error' => 'No PDF text provided'], Response::HTTP_BAD_REQUEST);
        }

        // Get parent transaction to validate amount
        $parentTransaction = $this->transactionRepository->find($transactionId);
        if (!$parentTransaction) {
            return $this->json(['error' => 'Transaction not found'], Response::HTTP_NOT_FOUND);
        }

        // Parse transactions
        $parsedTransactions = $this->pdfParser->parseIngCreditCardStatement($pdfText);

        // Calculate total
        $total = array_sum(array_column($parsedTransactions, 'amount'));
        $parentAmountStr = str_replace(',', '.', $this->transactionMapper->toDTO($parentTransaction)->amount);
        $parentAmount = floatval($parentAmountStr);

        // Validate
        $valid = $this->pdfParser->validateTotal($parsedTransactions, $parentAmount);

        return $this->json([
            'transactions' => $parsedTransactions,
            'total' => round($total, 2),
            'parentAmount' => $parentAmount,
            'valid' => $valid,
            'count' => count($parsedTransactions)
        ]);
    }

    /**
     * Create splits from parsed data
     */
    #[Route('/{transactionId}/splits', name: 'create_splits', methods: ['POST'])]
    #[OA\Post(
        path: '/api/account/{accountId}/transaction/{transactionId}/splits',
        description: 'Create split transactions from parsed credit card data',
        summary: 'Create transaction splits',
        requestBody: new OA\RequestBody(
            description: 'Split transaction data',
            required: true,
            content: new OA\JsonContent(
                required: ['splits'],
                properties: [
                    new OA\Property(
                        property: 'splits',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'date', type: 'string', format: 'date'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'amount', type: 'number'),
                                new OA\Property(property: 'transaction_type', type: 'string'),
                                new OA\Property(property: 'mutation_type', type: 'string'),
                                new OA\Property(property: 'transaction_code', type: 'string'),
                                new OA\Property(property: 'notes', type: 'string'),
                                new OA\Property(property: 'tag', type: 'string')
                            ]
                        )
                    )
                ]
            )
        ),
        tags: ['Transaction Splits'],
        responses: [
            new OA\Response(response: 201, description: 'Splits created successfully'),
            new OA\Response(response: 400, description: 'Invalid split data or amounts don\'t match')
        ]
    )]
    public function createSplits(int $accountId, int $transactionId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        $splits = $data['splits'] ?? [];

        if (empty($splits)) {
            return $this->json(['error' => 'No split data provided'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $parentTransaction = $this->splitService->createSplitsFromParsedData($transactionId, $splits);

            // Return parent with splits
            $parentDto = $this->transactionMapper->toDTO($parentTransaction);

            $splitsDto = array_map(
                fn($split) => $this->transactionMapper->toDTO($split),
                $parentTransaction->getSplits()->toArray()
            );

            return $this->json([
                'parent' => $parentDto,
                'splits' => $splitsDto,
                'message' => 'Splits created successfully'
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get splits for a transaction
     */
    #[Route('/{transactionId}/splits', name: 'get_splits', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/transaction/{transactionId}/splits',
        description: 'Get all split transactions for a parent transaction',
        summary: 'Get transaction splits',
        tags: ['Transaction Splits'],
        responses: [
            new OA\Response(response: 200, description: 'List of split transactions')
        ]
    )]
    public function getSplits(int $accountId, int $transactionId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            $splits = $this->splitService->getSplits($transactionId);

            $splitsDto = array_map(
                fn($split) => $this->transactionMapper->toDTO($split),
                $splits
            );

            return $this->json($splitsDto);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Delete all splits from a transaction
     */
    #[Route('/{transactionId}/splits', name: 'delete_splits', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/account/{accountId}/transaction/{transactionId}/splits',
        description: 'Delete all split transactions from a parent transaction',
        summary: 'Delete transaction splits',
        tags: ['Transaction Splits'],
        responses: [
            new OA\Response(response: 204, description: 'Splits deleted successfully')
        ]
    )]
    public function deleteSplits(int $accountId, int $transactionId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            $this->splitService->deleteSplits($transactionId);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete a single split transaction
     */
    #[Route('/split/{splitId}', name: 'delete_single_split', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/account/{accountId}/transaction/split/{splitId}',
        description: 'Delete a single split transaction',
        summary: 'Delete a split',
        tags: ['Transaction Splits'],
        responses: [
            new OA\Response(response: 204, description: 'Split deleted successfully')
        ]
    )]
    public function deleteSingleSplit(int $accountId, int $splitId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            $this->splitService->deleteSplit($splitId);
            return $this->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
