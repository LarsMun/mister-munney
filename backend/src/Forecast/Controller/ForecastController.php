<?php

namespace App\Forecast\Controller;

use App\Account\Repository\AccountRepository;
use App\Forecast\Service\ForecastService;
use App\Shared\Controller\AccountOwnershipTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Forecast',
    description: 'Cashflow forecast beheer'
)]
#[Route('/api/account/{accountId}/forecast')]
class ForecastController extends AbstractController
{
    use AccountOwnershipTrait;

    public function __construct(
        private ForecastService $forecastService,
        private AccountRepository $accountRepository,
    ) {}

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    #[OA\Get(
        path: '/api/account/{accountId}/forecast',
        summary: 'Haal de cashflow forecast op voor een maand',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'month',
                in: 'query',
                required: false,
                description: 'Maand in YYYY-MM formaat (default: huidige maand)',
                schema: new OA\Schema(type: 'string', example: '2025-11')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Forecast data'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Geen toegang')
        ]
    )]
    #[Route('', name: 'forecast_get', methods: ['GET'])]
    public function getForecast(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $month = $request->query->get('month', date('Y-m'));
        $forecast = $this->forecastService->getForecast($accountId, $month);

        return $this->json($forecast);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/forecast/available',
        summary: 'Haal beschikbare budgetten en categorieën op',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Beschikbare items'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Geen toegang')
        ]
    )]
    #[Route('/available', name: 'forecast_available', methods: ['GET'])]
    public function getAvailable(int $accountId): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        return $this->json([
            'budgets' => $this->forecastService->getAvailableBudgets($accountId),
            'categories' => $this->forecastService->getAvailableCategories($accountId),
        ]);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/forecast/items',
        summary: 'Voeg een item toe aan de forecast',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'expectedAmount'],
                properties: [
                    new OA\Property(property: 'budgetId', type: 'integer', nullable: true),
                    new OA\Property(property: 'categoryId', type: 'integer', nullable: true),
                    new OA\Property(property: 'type', type: 'string', enum: ['INCOME', 'EXPENSE']),
                    new OA\Property(property: 'expectedAmount', type: 'number', example: 1500.00)
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Item toegevoegd'),
            new OA\Response(response: 400, description: 'Ongeldige input'),
            new OA\Response(response: 401, description: 'Niet geauthenticeerd'),
            new OA\Response(response: 403, description: 'Geen toegang')
        ]
    )]
    #[Route('/items', name: 'forecast_add_item', methods: ['POST'])]
    public function addItem(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);

        $type = $data['type'] ?? 'EXPENSE';
        $expectedAmount = $data['expectedAmount'] ?? 0;
        $expectedAmountInCents = (int) round($expectedAmount * 100);

        if (isset($data['budgetId'])) {
            $item = $this->forecastService->addBudget(
                $accountId,
                (int) $data['budgetId'],
                $type,
                $expectedAmountInCents
            );
        } elseif (isset($data['categoryId'])) {
            $item = $this->forecastService->addCategory(
                $accountId,
                (int) $data['categoryId'],
                $type,
                $expectedAmountInCents
            );
        } else {
            return $this->json(['error' => 'budgetId of categoryId is verplicht'], 400);
        }

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'type' => $item->getType(),
            'expectedAmount' => $expectedAmount,
            'position' => $item->getPosition(),
        ], 201);
    }

    #[OA\Put(
        path: '/api/account/{accountId}/forecast/items/{itemId}',
        summary: 'Update een forecast item',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'itemId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'expectedAmount', type: 'number'),
                    new OA\Property(property: 'customName', type: 'string', nullable: true),
                    new OA\Property(property: 'position', type: 'integer'),
                    new OA\Property(property: 'type', type: 'string', enum: ['INCOME', 'EXPENSE'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Item bijgewerkt'),
            new OA\Response(response: 404, description: 'Item niet gevonden')
        ]
    )]
    #[Route('/items/{itemId}', name: 'forecast_update_item', methods: ['PUT'])]
    public function updateItem(int $accountId, int $itemId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        $item = $this->forecastService->updateItem($itemId, $data);

        return $this->json([
            'id' => $item->getId(),
            'name' => $item->getName(),
            'type' => $item->getType(),
            'expectedAmount' => $item->getExpectedAmountInCents() / 100,
            'position' => $item->getPosition(),
        ]);
    }

    #[OA\Delete(
        path: '/api/account/{accountId}/forecast/items/{itemId}',
        summary: 'Verwijder een forecast item',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'itemId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Item verwijderd'),
            new OA\Response(response: 404, description: 'Item niet gevonden')
        ]
    )]
    #[Route('/items/{itemId}', name: 'forecast_delete_item', methods: ['DELETE'])]
    public function deleteItem(int $accountId, int $itemId): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $this->forecastService->removeItem($itemId);

        return $this->json(null, 204);
    }

    #[OA\Put(
        path: '/api/account/{accountId}/forecast/positions',
        summary: 'Update posities van forecast items (drag & drop)',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'array',
                items: new OA\Items(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'position', type: 'integer'),
                        new OA\Property(property: 'type', type: 'string', enum: ['INCOME', 'EXPENSE'])
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Posities bijgewerkt')
        ]
    )]
    #[Route('/positions', name: 'forecast_update_positions', methods: ['PUT'])]
    public function updatePositions(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $positions = json_decode($request->getContent(), true);
        $this->forecastService->updatePositions($positions);

        return $this->json(['success' => true]);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/forecast/items/{itemId}/reset-to-median',
        summary: 'Reset een forecast item naar de historische mediaan',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'itemId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item gereset naar mediaan'),
            new OA\Response(response: 404, description: 'Item niet gevonden')
        ]
    )]
    #[Route('/items/{itemId}/reset-to-median', name: 'forecast_reset_item_to_median', methods: ['POST'])]
    public function resetItemToMedian(int $accountId, int $itemId): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $item = $this->forecastService->resetItemToMedian($itemId);

        return $this->json([
            'id' => $item->getId(),
            'expectedAmount' => $item->getExpectedAmountInCents() / 100,
        ]);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/forecast/reset-to-median',
        summary: 'Reset alle forecast items van een type naar hun mediaan',
        tags: ['Forecast'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['INCOME', 'EXPENSE'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Items gereset naar mediaan'),
            new OA\Response(response: 400, description: 'Ongeldig type')
        ]
    )]
    #[Route('/reset-to-median', name: 'forecast_reset_type_to_median', methods: ['POST'])]
    public function resetTypeToMedian(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        $type = $data['type'] ?? null;

        if (!in_array($type, ['INCOME', 'EXPENSE'], true)) {
            return $this->json(['error' => 'Type moet INCOME of EXPENSE zijn'], 400);
        }

        $count = $this->forecastService->resetTypeToMedian($accountId, $type);

        return $this->json([
            'success' => true,
            'updatedCount' => $count,
        ]);
    }
}
