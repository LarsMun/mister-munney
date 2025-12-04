<?php

namespace App\Transaction\Controller;

use App\Account\Repository\AccountRepository;
use App\Shared\Controller\AccountOwnershipTrait;
use App\Transaction\Service\TransactionImportService;
use App\Transaction\Service\PayPalImportService;
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
    private AccountRepository $accountRepository;

    public function __construct(
        TransactionImportService $transactionImportService,
        PayPalImportService $payPalImportService,
        AccountRepository $accountRepository
    ) {
        $this->transactionImportService = $transactionImportService;
        $this->payPalImportService = $payPalImportService;
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
        $result = $this->transactionImportService->import($file, $parentAccount);

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
}