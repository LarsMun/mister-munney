<?php

namespace App\Transaction\Controller;

use App\Transaction\Service\TransactionImportService;
use App\Transaction\Service\PayPalImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Transactions import',
    description: 'Importeren van transactions via CSV-bestand'
)]
#[Route('/api/transactions')]
class TransactionImportController extends AbstractController
{
    private TransactionImportService $transactionImportService;
    private PayPalImportService $payPalImportService;

    public function __construct(
        TransactionImportService $transactionImportService,
        PayPalImportService $payPalImportService
    ) {
        $this->transactionImportService = $transactionImportService;
        $this->payPalImportService = $payPalImportService;
    }

    #[OA\Post(
        path: '/api/transactions/import',
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
        responses: [
            new OA\Response(response: 201, description: 'Transacties geïmporteerd'),
            new OA\Response(response: 400, description: 'Geen bestand ontvangen')
        ]
    )]
    #[Route('/import', name: 'import', methods: ['POST'])]
    public function importCsv(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            throw new BadRequestHttpException('No file uploaded');
        }

        $result = $this->transactionImportService->import($file);

        return $this->json($result, 201);
    }

    #[OA\Post(
        path: '/api/transactions/import-paypal',
        summary: 'Importeer PayPal transacties via copy-paste',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['pastedText', 'accountId'],
                properties: [
                    new OA\Property(
                        property: 'pastedText',
                        type: 'string',
                        description: 'Copy-pasted text from PayPal website'
                    ),
                    new OA\Property(
                        property: 'accountId',
                        type: 'integer',
                        description: 'Account ID to match against'
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Transactions import'],
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
            new OA\Response(response: 400, description: 'Geen tekst ontvangen')
        ]
    )]
    #[Route('/import-paypal', name: 'import_paypal', methods: ['POST'])]
    public function importPayPal(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['pastedText']) || !isset($data['accountId'])) {
            throw new BadRequestHttpException('Missing pastedText or accountId');
        }

        $pastedText = $data['pastedText'];
        $accountId = (int) $data['accountId'];

        if (empty($pastedText)) {
            throw new BadRequestHttpException('Pasted text cannot be empty');
        }

        $result = $this->payPalImportService->importFromPastedText($pastedText, $accountId);

        return $this->json($result, 201);
    }
}