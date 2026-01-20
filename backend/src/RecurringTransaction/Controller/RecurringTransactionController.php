<?php

namespace App\RecurringTransaction\Controller;

use App\Account\Repository\AccountRepository;
use App\RecurringTransaction\DTO\RecurringTransactionDTO;
use App\RecurringTransaction\DTO\UpcomingTransactionDTO;
use App\RecurringTransaction\Mapper\RecurringTransactionMapper;
use App\RecurringTransaction\Service\RecurringTransactionService;
use App\Shared\Controller\AccountOwnershipTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Recurring Transactions',
    description: 'Beheer van terugkerende transacties'
)]
#[Route('/api/account/{accountId}/recurring-transactions')]
class RecurringTransactionController extends AbstractController
{
    use AccountOwnershipTrait;

    public function __construct(
        private RecurringTransactionService $service,
        private RecurringTransactionMapper $mapper,
        private AccountRepository $accountRepository,
    ) {
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    #[OA\Get(
        path: '/api/account/{accountId}/recurring-transactions',
        summary: 'Haal alle terugkerende transacties op voor een account',
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'frequency',
                description: 'Filter op frequentie',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['weekly', 'biweekly', 'monthly', 'quarterly', 'yearly'])
            ),
            new OA\Parameter(
                name: 'active',
                description: 'Filter op actieve status',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van terugkerende transacties',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                )
            )
        ]
    )]
    #[Route('', name: 'get_recurring_transactions', methods: ['GET'])]
    public function list(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $frequency = $request->query->get('frequency');
        $active = $request->query->get('active');
        $isActive = $active !== null ? filter_var($active, FILTER_VALIDATE_BOOLEAN) : null;

        $items = $this->service->getAllByAccount($accountId, $frequency, $isActive);
        $dtos = $this->mapper->toDtoList($items);

        return $this->json($dtos);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/recurring-transactions/grouped',
        summary: 'Haal terugkerende transacties gegroepeerd per frequentie',
        tags: ['Recurring Transactions'],
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
                description: 'Gegroepeerde terugkerende transacties',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'weekly',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                        ),
                        new OA\Property(
                            property: 'biweekly',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                        ),
                        new OA\Property(
                            property: 'monthly',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                        ),
                        new OA\Property(
                            property: 'quarterly',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                        ),
                        new OA\Property(
                            property: 'yearly',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                        )
                    ]
                )
            )
        ]
    )]
    #[Route('/grouped', name: 'get_recurring_transactions_grouped', methods: ['GET'])]
    public function listGrouped(int $accountId): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $grouped = $this->service->getGroupedByFrequency($accountId);

        $result = [];
        foreach ($grouped as $frequency => $items) {
            $result[$frequency] = $this->mapper->toDtoList($items);
        }

        return $this->json($result);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/recurring-transactions/summary',
        summary: 'Haal samenvatting op van terugkerende transacties',
        tags: ['Recurring Transactions'],
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
                description: 'Samenvatting',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total', type: 'integer', example: 15),
                        new OA\Property(property: 'active', type: 'integer', example: 12),
                        new OA\Property(property: 'monthlyDebit', type: 'number', format: 'float', example: 450.50),
                        new OA\Property(property: 'monthlyCredit', type: 'number', format: 'float', example: 2500.00)
                    ]
                )
            )
        ]
    )]
    #[Route('/summary', name: 'get_recurring_transactions_summary', methods: ['GET'])]
    public function summary(int $accountId): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $summary = $this->service->getSummary($accountId);

        // Convert cents to euros for the response
        return $this->json([
            'total' => $summary['total'],
            'active' => $summary['active'],
            'monthlyDebit' => $summary['monthlyDebit'] / 100,
            'monthlyCredit' => $summary['monthlyCredit'] / 100,
        ]);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/recurring-transactions/upcoming',
        summary: 'Haal verwachte transacties op voor de komende dagen',
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'days',
                description: 'Aantal dagen vooruit kijken',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 365, example: 30)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Verwachte transacties',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: UpcomingTransactionDTO::class))
                )
            )
        ]
    )]
    #[Route('/upcoming', name: 'get_upcoming_transactions', methods: ['GET'])]
    public function upcoming(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $days = (int) $request->query->get('days', 30);
        $days = max(1, min(365, $days));

        $items = $this->service->getUpcoming($accountId, $days);
        $dtos = $this->mapper->toUpcomingDtoList($items);

        return $this->json($dtos);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/recurring-transactions/{id}',
        summary: 'Haal een specifieke terugkerende transactie op',
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de terugkerende transactie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Terugkerende transactie details',
                content: new OA\JsonContent(ref: new Model(type: RecurringTransactionDTO::class))
            ),
            new OA\Response(response: 404, description: 'Niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'get_recurring_transaction', methods: ['GET'])]
    public function get(int $accountId, int $id): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $item = $this->service->getById($id, $accountId);
        $dto = $this->mapper->toDto($item);

        return $this->json($dto);
    }

    #[OA\Patch(
        path: '/api/account/{accountId}/recurring-transactions/{id}',
        summary: 'Werk een terugkerende transactie bij',
        requestBody: new OA\RequestBody(
            description: 'Te wijzigen velden',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'displayName', type: 'string', example: 'Netflix Subscription'),
                    new OA\Property(property: 'isActive', type: 'boolean', example: true),
                    new OA\Property(property: 'categoryId', type: 'integer', example: 5, nullable: true)
                ]
            )
        ),
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de terugkerende transactie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bijgewerkte terugkerende transactie',
                content: new OA\JsonContent(ref: new Model(type: RecurringTransactionDTO::class))
            ),
            new OA\Response(response: 404, description: 'Niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'update_recurring_transaction', methods: ['PATCH'])]
    public function update(int $accountId, int $id, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $item = $this->service->update($id, $accountId, $data);
        $dto = $this->mapper->toDto($item);

        return $this->json($dto);
    }

    #[OA\Delete(
        path: '/api/account/{accountId}/recurring-transactions/{id}',
        summary: 'Deactiveer een terugkerende transactie',
        description: 'Soft delete: zet isActive op false',
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de terugkerende transactie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Succesvol gedeactiveerd'),
            new OA\Response(response: 404, description: 'Niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'delete_recurring_transaction', methods: ['DELETE'])]
    public function delete(int $accountId, int $id): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $this->service->deactivate($id, $accountId);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/recurring-transactions/detect',
        summary: 'Start detectie van terugkerende transacties',
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'force',
                description: 'Verwijder bestaande patronen en detecteer opnieuw',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean', example: false)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Gedetecteerde terugkerende transacties',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'detected', type: 'integer', example: 5),
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: RecurringTransactionDTO::class))
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Get(
        path: '/api/account/{accountId}/recurring-transactions/{id}/transactions',
        summary: 'Haal transacties op die bij dit terugkerende patroon horen',
        tags: ['Recurring Transactions'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de terugkerende transactie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'limit',
                description: 'Maximum aantal transacties',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van gekoppelde transacties'
            ),
            new OA\Response(response: 404, description: 'Niet gevonden')
        ]
    )]
    #[Route('/{id}/transactions', name: 'get_recurring_transaction_transactions', methods: ['GET'])]
    public function getTransactions(int $accountId, int $id, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $limit = (int) $request->query->get('limit', 20);
        $limit = max(1, min(100, $limit));

        $transactions = $this->service->getLinkedTransactions($id, $accountId, $limit);

        return $this->json($transactions);
    }

    #[Route('/detect', name: 'detect_recurring_transactions', methods: ['POST'])]
    public function detect(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $force = filter_var($request->query->get('force', false), FILTER_VALIDATE_BOOLEAN);

        $detected = $this->service->detect($accountId, $force);
        $dtos = $this->mapper->toDtoList($detected);

        return $this->json([
            'detected' => count($detected),
            'items' => $dtos,
        ]);
    }
}
