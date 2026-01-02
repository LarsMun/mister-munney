<?php

namespace App\Transaction\Controller;

use App\Account\Repository\AccountRepository;
use App\Shared\Controller\AccountOwnershipTrait;
use App\Transaction\Service\TransactionImportService;
use App\Transaction\Service\PayPalImportService;
use App\Transaction\Service\PayPalManualMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Transactions import',
    description: 'Importeren van transactions via CSV-bestand'
)]
#[Route('/api/account/{accountId}/transactions')]
class TransactionImportController extends AbstractController
{
    use AccountOwnershipTrait;

    private TransactionImportService $transactionImportService;
    private PayPalImportService $payPalImportService;
    private PayPalManualMatchService $payPalManualMatchService;
    private AccountRepository $accountRepository;

    public function __construct(
        TransactionImportService $transactionImportService,
        PayPalImportService $payPalImportService,
        PayPalManualMatchService $payPalManualMatchService,
        AccountRepository $accountRepository
    ) {
        $this->transactionImportService = $transactionImportService;
        $this->payPalImportService = $payPalImportService;
        $this->payPalManualMatchService = $payPalManualMatchService;
        $this->accountRepository = $accountRepository;
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }


    #[OA\Post(
        path: '/api/account/{accountId}/transactions/import',
        summary: 'Importeer transacties via CSV',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['Transactions import'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvoor transacties worden geïmporteerd',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 201, description: 'Transacties geïmporteerd'),
            new OA\Response(response: 400, description: 'Geen bestand ontvangen'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Toegang geweigerd')
        ]
    )]
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function importCsv(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $file = $request->files->get('file');

        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        $parentAccount = $this->accountRepository->getById($accountId);
        $user = $this->getUser();
        $result = $this->transactionImportService->importForUserWithParent($file, $user, $parentAccount);

        return $this->json($result, 201);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/transactions/import-paypal',
        summary: 'Importeer PayPal transacties via copy-paste',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pastedText'],
                properties: [
                    new OA\Property(
                        property: 'pastedText',
                        type: 'string',
                        description: 'Copy-pasted text from PayPal website'
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Transactions import'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvoor transacties worden geïmporteerd',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'PayPal transacties geïmporteerd',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'parsed', type: 'integer'),
                        new OA\Property(property: 'matched', type: 'integer'),
                        new OA\Property(property: 'imported', type: 'integer'),
                        new OA\Property(property: 'skipped', type: 'integer')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Geen tekst ontvangen'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Toegang geweigerd')
        ]
    )]
    #[Route('/import-paypal', name: 'import_paypal', methods: ['POST'])]
    public function importPayPal(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['pastedText'])) {
            throw new BadRequestHttpException('Missing pastedText');
        }

        $pastedText = $data['pastedText'];

        if (empty($pastedText)) {
            throw new BadRequestHttpException('Pasted text cannot be empty');
        }

        $result = $this->payPalImportService->importFromPastedText($pastedText, $accountId);

        return $this->json($result, 201);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/transactions/import-paypal-csv',
        summary: 'Importeer PayPal transacties via CSV-bestand',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['Transactions import'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvoor transacties worden geïmporteerd',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'PayPal transacties geïmporteerd',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'parsed', type: 'integer'),
                        new OA\Property(property: 'matched', type: 'integer'),
                        new OA\Property(property: 'imported', type: 'integer'),
                        new OA\Property(property: 'skipped', type: 'integer')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Geen bestand ontvangen'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Toegang geweigerd')
        ]
    )]
    #[Route('/import-paypal-csv', name: 'import_paypal_csv', methods: ['POST'])]
    public function importPayPalCsv(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $file = $request->files->get('file');

        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        $csvContent = file_get_contents($file->getPathname());

        if (empty($csvContent)) {
            throw new BadRequestHttpException('CSV file is empty');
        }

        $result = $this->payPalImportService->importFromCsv($csvContent, $accountId);

        return $this->json($result, 201);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/transactions/parse-paypal-csv',
        summary: 'Parse PayPal CSV without auto-matching (for manual matching)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(
                            property: 'file',
                            type: 'string',
                            format: 'binary'
                        )
                    ],
                    type: 'object'
                )
            )
        ),
        tags: ['Transactions import'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CSV parsed successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'count', type: 'integer'),
                        new OA\Property(property: 'totalParsed', type: 'integer'),
                        new OA\Property(property: 'alreadyLinked', type: 'integer')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Geen bestand ontvangen'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Toegang geweigerd')
        ]
    )]
    #[Route('/parse-paypal-csv', name: 'parse_paypal_csv', methods: ['POST'])]
    public function parsePayPalCsv(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $file = $request->files->get('file');

        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        $csvContent = file_get_contents($file->getPathname());

        if (empty($csvContent)) {
            throw new BadRequestHttpException('CSV file is empty');
        }

        $result = $this->payPalManualMatchService->parseWithoutMatching($csvContent, $accountId);

        return $this->json($result);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/transactions/paypal-unmatched',
        summary: 'Get bank transactions with PayPal that have no splits',
        tags: ['Transactions import'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of unmatched PayPal bank transactions',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'transactions', type: 'array', items: new OA\Items(type: 'object'))
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Toegang geweigerd')
        ]
    )]
    #[Route('/paypal-unmatched', name: 'paypal_unmatched', methods: ['GET'])]
    public function getPayPalUnmatched(int $accountId): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $transactions = $this->payPalManualMatchService->getUnmatchedBankTransactions($accountId);

        return $this->json(['transactions' => $transactions]);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/transactions/{transactionId}/paypal-link',
        summary: 'Create child transactions from manually selected PayPal items',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['items'],
                properties: [
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'date', type: 'string'),
                                new OA\Property(property: 'merchant', type: 'string'),
                                new OA\Property(property: 'amount', type: 'number'),
                                new OA\Property(property: 'reference', type: 'string')
                            ],
                            type: 'object'
                        )
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Transactions import'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'transactionId',
                description: 'ID van de bank transactie (parent)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Child transactions created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'created', type: 'integer'),
                        new OA\Property(property: 'parentId', type: 'integer')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Toegang geweigerd')
        ]
    )]
    #[Route('/{transactionId}/paypal-link', name: 'paypal_link', methods: ['POST'])]
    public function createPayPalLink(int $accountId, int $transactionId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['items']) || !is_array($data['items'])) {
            throw new BadRequestHttpException('Missing or invalid items array');
        }

        if (empty($data['items'])) {
            throw new BadRequestHttpException('Items array cannot be empty');
        }

        $result = $this->payPalManualMatchService->createManualLinks($transactionId, $data['items'], $accountId);

        return $this->json($result, 201);
    }
}