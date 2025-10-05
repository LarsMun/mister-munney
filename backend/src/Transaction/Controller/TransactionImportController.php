<?php

namespace App\Transaction\Controller;

use App\Transaction\Service\TransactionImportService;
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

    public function __construct(TransactionImportService $transactionImportService)
    {
        $this->transactionImportService = $transactionImportService;
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
}