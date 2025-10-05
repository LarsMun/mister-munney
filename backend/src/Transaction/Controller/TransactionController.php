<?php

namespace App\Transaction\Controller;

use App\Entity\Transaction;
use App\Mapper\PayloadMapper;
use App\Transaction\DTO\AssignSavingsAccountDTO;
use App\Transaction\DTO\SetCategoryDTO;
use App\Transaction\DTO\TransactionDTO;
use App\Transaction\DTO\TransactionFilterDTO;
use App\Transaction\Mapper\TransactionFilterMapper;
use App\Transaction\Mapper\TransactionMapper;
use App\Transaction\Service\TransactionService;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(
    name: 'Transactions',
    description: 'Beheer van transactions'
)]

#[Route('/api/account/{accountId}/transactions')]
class TransactionController extends AbstractController
{
    private TransactionService $transactionService;
    private TransactionMapper $transactionMapper;
    private PayloadMapper $payloadMapper;
    private ValidatorInterface $validator;

    public function __construct(TransactionService $transactionService, TransactionMapper $transactionMapper, PayloadMapper $payloadMapper, ValidatorInterface $validator)
    {
        $this->transactionService = $transactionService;
        $this->transactionMapper = $transactionMapper;
        $this->payloadMapper = $payloadMapper;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/api/account/{accountId}/transactions',
        summary: 'Haal een lijst van transacties op voor een account',
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvoor transacties worden opgehaald',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'search',
                description: 'Zoekterm in omschrijving, tegenrekening of notities',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'Albert Heijn')
            ),
            new OA\Parameter(
                name: 'startDate',
                description: 'Startdatum in YYYY-MM-DD formaat',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')
            ),
            new OA\Parameter(
                name: 'endDate',
                description: 'Einddatum in YYYY-MM-DD formaat',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-31')
            ),
            new OA\Parameter(
                name: 'minAmount',
                description: 'Minimale transactiebedrag',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'number', example: 10.00)
            ),
            new OA\Parameter(
                name: 'maxAmount',
                description: 'Maximale transactiebedrag',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'number', example: 100.00)
            ),
            new OA\Parameter(
                name: 'transactionType',
                description: 'Type transactie: credit of debit',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['credit', 'debit'], example: 'debit')
            ),
            new OA\Parameter(
                name: 'sortBy',
                description: 'Sorteerveld: bijv. date of amount',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'date')
            ),
            new OA\Parameter(
                name: 'sortDirection',
                description: 'Sorteerrichting: ASC of DESC',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['ASC', 'DESC'], example: 'DESC')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van transacties',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'summary', type: 'object'),
                        new OA\Property(property: 'treeMapData', type: 'object'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: TransactionDTO::class))
                        )
                    ],
                    type: 'object'
                )
            )
        ]
    )]
    #[Route('', name: 'get_transactions', methods: ['GET'])]
    public function getTransactions(
        int $accountId,
        Request $request,
        ValidatorInterface $validator,
        TransactionFilterMapper $filterMapper
    ): JsonResponse {
        $filter = $filterMapper->fromRequest($request, $accountId);

        $errors = $validator->validate($filter);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getPropertyPath() . ': ' . $error->getMessage();
            }

            return $this->json(['errors' => $messages], 400);
        }

        $result = $this->transactionService->findWithSummary($filter);

        $dtoList = array_map(fn(Transaction $t) => $this->transactionMapper->toDto($t), $result['data']);

        return $this->json([
            'summary' => $result['summary'],
            'treeMapData' => $result['treeMapData'],
            'data' => $dtoList,
        ], 200);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/transactions/months',
        summary: 'Beschikbare transactie-maanden per account',
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Unieke maanden waarin transacties zijn gedaan (YYYY-MM-formaat)",
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(type: 'string', example: '2024-03')
                )
            ),
            new OA\Response(response: 404, description: "Account niet gevonden")
        ]
    )]
    #[Route('/months', name: 'get_transaction_months', methods: ['GET'])]
    public function getAvailableMonths(int $accountId): JsonResponse
    {
        return $this->json(
            $this->transactionService->getAvailableMonths($accountId)
        );
    }

    #[OA\Patch(
        path: '/api/account/{accountId}/transactions/{id}/category',
        summary: 'Koppel een categorie aan een transactie',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: SetCategoryDTO::class))
        ),
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Categorie succesvol aan transactie verbonden'),
            new OA\Response(response: 400, description: 'Ongeldige input'),
            new OA\Response(response: 404, description: 'Transactie niet gevonden')
        ]
    )]
    #[Route('/{id}/category', name: 'transaction_set_category', methods: ['PATCH'])]
    public function setCategory(
        int $accountId,
        int $id,
        Request $request
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $dto = $this->payloadMapper->map($data, new SetCategoryDTO(), true);

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        // Als er vanuit frontend 0 wordt gestuurd, verwijderen de categorie uit de transactie
        if ($dto->categoryId === 0) {
            $this->transactionService->removeCategory($id);
        } else {
            $this->transactionService->setCategory($id, $dto->categoryId);
        }


        return new JsonResponse(null, 204); // ✅ Geen content
    }

    #[OA\Post(
        path: '/api/account/{accountId}/transactions/bulk-assign-category',
        summary: 'Wijs een categorie toe aan meerdere transacties',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['transactionIds', 'categoryId'],
                properties: [
                    new OA\Property(
                        property: 'transactionIds',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3]
                    ),
                    new OA\Property(
                        property: 'categoryId',
                        type: 'integer',
                        example: 5
                    )
                ]
            )
        ),
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Categorieën succesvol toegewezen'),
            new OA\Response(response: 400, description: 'Ongeldige input'),
        ]
    )]
    #[Route('/bulk-assign-category', name: 'bulk_assign_category', methods: ['POST'])]
    public function bulkAssignCategory(int $accountId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['transactionIds'], $data['categoryId'])) {
            throw new BadRequestHttpException('Verplicht veld ontbreekt.');
        }

        $transactionIds = $data['transactionIds'];
        $categoryId = $data['categoryId'];

        $this->transactionService->bulkAssignCategory($transactionIds, $categoryId);

        return new JsonResponse(null, 204);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/transactions/bulk-remove-category',
        summary: 'Verwijder categorie van meerdere transacties',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['transactionIds'],
                properties: [
                    new OA\Property(
                        property: 'transactionIds',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3]
                    )
                ]
            )
        ),
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Categorieën succesvol verwijderd'),
            new OA\Response(response: 400, description: 'Ongeldige input'),
        ]
    )]
    #[Route('/bulk-remove-category', name: 'bulk_remove_category', methods: ['POST'])]
    public function bulkRemoveCategory(int $accountId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['transactionIds'])) {
            throw new BadRequestHttpException('Verplicht veld ontbreekt.');
        }

        $transactionIds = $data['transactionIds'];

        $this->transactionService->bulkRemoveCategory($transactionIds);

        return new JsonResponse(null, 204);
    }

    #[OA\Patch(
        path: '/api/account/{accountId}/transactions/{id}/assign_savings',
        summary: 'Wijs een spaarrekening toe aan een transactie',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['savingsAccountId'],
                properties: [
                    new OA\Property(
                        property: 'savingsAccountId',
                        type: 'integer',
                        maximum: 2147483647,
                        minimum: 1,
                        example: 2
                    )
                ]
            )
        ),
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van de betaalrekening',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de transactie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'De bijgewerkte transactie',
                content: new OA\JsonContent(ref: new Model(type: TransactionDTO::class))
            ),
            new OA\Response(response: 404, description: 'Transactie of spaarrekening niet gevonden')
        ]
    )]
    #[Route('/{id}/assign_savings', name: 'assign_savings_account', methods: ['PATCH'])]
    public function assignSavingsAccount(
        int $accountId,
        int $id,
        Request $request,
        PayloadMapper $payloadMapper,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $dto = $payloadMapper->map($data, new AssignSavingsAccountDTO(), true);

        $errors = $validator->validate($dto);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $transaction = $this->transactionService->setSavingsAccount($id, $dto->savingsAccountId);

        $dto = $this->transactionMapper->toDTO($transaction);
        return new JsonResponse($dto, 200);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/transactions/statistics/monthly-median',
        summary: 'Bereken verschillende statistieken van maandelijkse uitgaven',
        tags: ['Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'months',
                description: 'Aantal maanden terug (bijv. 6) of "all" voor alle maanden',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: '6')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verschillende statistieken van maandelijkse uitgaven',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'median',
                            description: 'Mediaan (middelste waarde)',
                            type: 'number',
                            format: 'float',
                            example: 1234.56
                        ),
                        new OA\Property(
                            property: 'trimmedMean',
                            description: 'Trimmed mean (gemiddelde na verwijderen hoogste/laagste 20%)',
                            type: 'number',
                            format: 'float',
                            example: 1200.00
                        ),
                        new OA\Property(
                            property: 'iqrMean',
                            description: 'IQR mean (gemiddelde na verwijderen statistische outliers)',
                            type: 'number',
                            format: 'float',
                            example: 1180.00
                        ),
                        new OA\Property(
                            property: 'weightedMedian',
                            description: 'Gewogen mediaan (recente maanden wegen zwaarder)',
                            type: 'number',
                            format: 'float',
                            example: 1250.00
                        ),
                        new OA\Property(
                            property: 'plainAverage',
                            description: 'Gewoon gemiddelde van alle maanden',
                            type: 'number',
                            format: 'float',
                            example: 1300.00
                        ),
                        new OA\Property(
                            property: 'monthCount',
                            description: 'Aantal maanden in de berekening',
                            type: 'integer',
                            example: 6
                        ),
                        new OA\Property(
                            property: 'monthlyTotals',
                            description: 'Totale uitgaven per maand',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'month', type: 'string', example: '2024-03'),
                                    new OA\Property(property: 'total', type: 'number', format: 'float', example: 1500.00)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige months parameter'),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/statistics/monthly-median', name: 'get_monthly_median_statistics', methods: ['GET'])]
    public function getMonthlyMedianStatistics(
        int $accountId,
        Request $request
    ): JsonResponse {
        $months = $request->query->get('months', 'all');

        $statistics = $this->transactionService->getMonthlyMedianStatistics($accountId, $months);

        return $this->json($statistics, 200);
    }
}