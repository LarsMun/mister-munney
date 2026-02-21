<?php

namespace App\Category\Controller;

use App\Account\Repository\AccountRepository;
use App\Budget\Service\BudgetInsightsService;
use App\Category\DTO\CategoryDTO;
use App\Category\DTO\CategoryWithTransactionsDTO;
use App\Category\Mapper\CategoryMapper;
use App\Category\Service\CategoryService;
use App\Shared\Controller\AccountOwnershipTrait;
use App\Transaction\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Categories',
    description: 'Beheer van categorieën'
)]
#[Route('/api/account/{accountId}/categories')]
class CategoryController extends AbstractController
{
    use AccountOwnershipTrait;

    private CategoryService $categoryService;
    private CategoryMapper $categoryMapper;
    private TransactionService $transactionService;
    private AccountRepository $accountRepository;
    private BudgetInsightsService $budgetInsightsService;

    public function __construct(
        CategoryService $categoryService,
        CategoryMapper $categoryMapper,
        TransactionService $transactionService,
        AccountRepository $accountRepository,
        BudgetInsightsService $budgetInsightsService
    )
    {
        $this->categoryService = $categoryService;
        $this->categoryMapper = $categoryMapper;
        $this->transactionService = $transactionService;
        $this->accountRepository = $accountRepository;
        $this->budgetInsightsService = $budgetInsightsService;
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    #[OA\Get(
        path: '/api/account/{accountId}/categories',
        summary: 'Haal alle categorieën op voor een account',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(
                    type: 'integer',
                    maximum: 2147483647,
                    minimum: 1,
                    example: 1
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van categorieën',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: CategoryDTO::class))
                )
            )
        ]
    )]
    #[Route('', name: 'get_all_categories', methods: ['GET'])]
    public function list(int $accountId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $categories = $this->categoryService->getAllByAccount($accountId);
        $dtos = array_map(fn($category) => $this->categoryMapper->toDto($category), $categories);

        return $this->json($dtos);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/categories/{id}',
        summary: 'Haal een specifieke categorie op',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categoriegegevens',
                content: new OA\JsonContent(ref: new Model(type: CategoryDTO::class))
            ),
            new OA\Response(response: 404, description: 'Categorie niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'get_category', methods: ['GET'])]
    public function get(int $accountId, int $id): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $category = $this->categoryService->getById($id, $accountId);
        $dto = $this->categoryMapper->toDto($category);

        return $this->json($dto);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/categories/{id}/with_transactions',
        summary: 'Haal een categorie op inclusief gekoppelde transacties',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 7)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categorie met gekoppelde transacties',
                content: new OA\JsonContent(ref: new Model(type: CategoryWithTransactionsDTO::class))
            ),
            new OA\Response(response: 404, description: 'Categorie niet gevonden')
        ]
    )]
    #[Route('/{id}/with_transactions', name: 'get_category_with_transactions', methods: ['GET'])]
    public function getWithTransactions(int $accountId, int $id): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $categoryDto = $this->categoryService->getWithTransactions($id, $accountId);
        return $this->json($categoryDto);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/categories',
        summary: 'Maak een nieuwe categorie aan',
        requestBody: new OA\RequestBody(
            description: 'Categoriegegevens (name is verplicht, icon en color zijn optioneel)',
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Apparaten'),
                    new OA\Property(property: 'icon', type: 'string', example: 'shopping-cart'),
                    new OA\Property(property: 'color', type: 'string', example: '#FF9900')
                ]
            )
        ),
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waaraan de categorie wordt gekoppeld',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Categorie succesvol aangemaakt',
                content: new OA\JsonContent(ref: new Model(type: CategoryDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer')
        ]
    )]
    #[Route('', name: 'create_category', methods: ['POST'])]
    public function create(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        $category = $this->categoryService->create($accountId, $data);
        $dto = $this->categoryMapper->toDto($category);

        return $this->json($dto, 201);
    }

    #[OA\Put(
        path: '/api/account/{accountId}/categories/{id}',
        summary: 'Werk een categorie bij',
        requestBody: new OA\RequestBody(
            description: 'Bijgewerkte categorienaam en optionele eigenschappen',
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Auto'),
                    new OA\Property(property: 'icon', type: 'string', example: 'car'),
                    new OA\Property(property: 'color', type: 'string', example: '#336699')
                ]
            )
        ),
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 7)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categorie succesvol bijgewerkt',
                content: new OA\JsonContent(ref: new Model(type: CategoryDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 404, description: 'Categorie niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'update_category', methods: ['PUT'])]
    public function update(int $accountId, int $id, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        $category = $this->categoryService->update($id, $accountId, $data);
        $dto = $this->categoryMapper->toDto($category);

        return $this->json($dto);
    }

    #[OA\Delete(
        path: '/api/account/{accountId}/categories/{id}',
        summary: 'Verwijder een categorie',
        description: 'Verwijdert een categorie alleen als er geen transacties aan gekoppeld zijn',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de te verwijderen categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 7)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Categorie succesvol verwijderd'),
            new OA\Response(response: 404, description: 'Categorie niet gevonden'),
            new OA\Response(
                response: 409,
                description: 'Categorie heeft nog gekoppelde transacties',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Cannot delete category with 45 linked transaction(s). Please merge this category into another category first.')
                    ]
                )
            )
        ]
    )]
    #[OA\Get(
        path: '/api/account/{accountId}/categories/{id}/preview-delete',
        summary: 'Preview van categorie verwijdering',
        description: 'Geeft informatie over wat er gebeurt bij het verwijderen van een categorie',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 7)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Delete preview informatie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'canDelete', type: 'boolean', example: false),
                        new OA\Property(property: 'transactionCount', type: 'integer', example: 45),
                        new OA\Property(property: 'patternCount', type: 'integer', example: 2),
                        new OA\Property(property: 'categoryName', type: 'string', example: 'Boodschappen'),
                        new OA\Property(property: 'message', type: 'string', example: 'This category has 45 linked transaction(s) and cannot be deleted.')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Categorie niet gevonden')
        ]
    )]
    #[Route('/{id}/preview-delete', name: 'preview_delete_category', methods: ['GET'])]
    public function previewDelete(int $accountId, int $id): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $preview = $this->categoryService->previewDelete($id, $accountId);
        return $this->json($preview);
    }

    #[Route('/{id}', name: 'delete_category', methods: ['DELETE'])]
    public function delete(int $accountId, int $id): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $this->categoryService->delete($id, $accountId);
        return $this->json(null, 204);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/categories/{sourceId}/merge-preview/{targetId}',
        summary: 'Preview van categorie merge operatie',
        description: 'Geeft informatie over wat er gebeurt bij het mergen van twee categorieën',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'sourceId',
                description: 'ID van de bron categorie (wordt verwijderd)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 5)
            ),
            new OA\Parameter(
                name: 'targetId',
                description: 'ID van de doel categorie (ontvangt transacties)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 3)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Merge preview informatie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'sourceCategory',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'name', type: 'string', example: 'Boodschappen oud'),
                                new OA\Property(property: 'color', type: 'string', example: '#FF0000'),
                                new OA\Property(property: 'icon', type: 'string', example: '/backend/icons/cart.svg')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'targetCategory',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 3),
                                new OA\Property(property: 'name', type: 'string', example: 'Boodschappen'),
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'transactionsToMove', type: 'integer', example: 245),
                        new OA\Property(property: 'totalAmount', type: 'number', format: 'float', example: -2450.50),
                        new OA\Property(
                            property: 'dateRange',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', format: 'date', example: '2024-01-01'),
                                new OA\Property(property: 'last', type: 'string', format: 'date', example: '2025-10-31')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(property: 'targetCurrentTransactionCount', type: 'integer', example: 143),
                        new OA\Property(property: 'targetNewTransactionCount', type: 'integer', example: 388)
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige merge (zelfde categorie, verkeerd account)'),
            new OA\Response(response: 404, description: 'Een van de categorieën niet gevonden')
        ]
    )]
    #[Route('/{sourceId}/merge-preview/{targetId}', name: 'preview_merge_categories', methods: ['GET'])]
    public function previewMerge(int $accountId, int $sourceId, int $targetId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $preview = $this->categoryService->previewMerge($sourceId, $targetId, $accountId);
        return $this->json($preview);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/categories/{sourceId}/merge/{targetId}',
        summary: 'Merge twee categorieën',
        description: 'Verplaatst alle transacties van source naar target en verwijdert source categorie',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'sourceId',
                description: 'ID van de bron categorie (wordt verwijderd)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 5)
            ),
            new OA\Parameter(
                name: 'targetId',
                description: 'ID van de doel categorie (ontvangt transacties)',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 3)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Merge succesvol',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'transactionsMoved', type: 'integer', example: 245),
                        new OA\Property(property: 'sourceDeleted', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Successfully merged 245 transaction(s) from "Boodschappen oud" to "Boodschappen"')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige merge (zelfde categorie, verkeerd account)'),
            new OA\Response(response: 404, description: 'Een van de categorieën niet gevonden')
        ]
    )]
    #[Route('/{sourceId}/merge/{targetId}', name: 'merge_categories', methods: ['POST'])]
    public function merge(int $accountId, int $sourceId, int $targetId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $result = $this->categoryService->mergeCategories($sourceId, $targetId, $accountId);
        return $this->json($result);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/transactions/statistics/by-category',
        summary: 'Haal statistieken per categorie op',
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
                description: 'Statistieken per categorie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'categories',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'categoryId', type: 'integer', example: 1),
                                    new OA\Property(property: 'categoryName', type: 'string', example: 'Boodschappen'),
                                    new OA\Property(property: 'categoryColor', type: 'string', example: '#FF5733'),
                                    new OA\Property(property: 'categoryIcon', type: 'string', example: 'shopping-cart'),
                                    new OA\Property(property: 'totalAmount', type: 'number', format: 'float', example: 1234.56),
                                    new OA\Property(property: 'transactionCount', type: 'integer', example: 45),
                                    new OA\Property(property: 'averagePerTransaction', type: 'number', format: 'float', example: 27.43),
                                    new OA\Property(property: 'averagePerMonth', type: 'number', format: 'float', example: 411.52),
                                    new OA\Property(property: 'percentageOfTotal', type: 'number', format: 'float', example: 35.5)
                                ]
                            )
                        ),
                        new OA\Property(property: 'monthCount', type: 'integer', example: 6),
                        new OA\Property(property: 'totalSpent', type: 'number', format: 'float', example: 3476.89)
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige months parameter'),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/statistics/by-category', name: 'get_category_statistics', methods: ['GET'])]
    public function getCategoryStatistics(
        int $accountId,
        Request $request
    ): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $months = $request->query->get('months', 'all');

        $statistics = $this->categoryService->getCategoryStatistics($accountId, $months);

        return $this->json($statistics, 200);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/categories/aggregate-statistics',
        summary: 'Bereken gemiddelde en mediaan voor een set categorieën',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['categoryIds'],
                properties: [
                    new OA\Property(property: 'categoryIds', type: 'array', items: new OA\Items(type: 'integer'), example: [1, 2, 3]),
                    new OA\Property(property: 'period', type: 'string', enum: ['6m', '1y', '2y', '3y', 'all'], example: '1y'),
                    new OA\Property(property: 'includeCurrentMonth', type: 'boolean', example: false),
                    new OA\Property(property: 'includeBreakdown', type: 'boolean', example: false)
                ]
            )
        ),
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aggregate statistics'),
            new OA\Response(response: 400, description: 'Ongeldige invoer')
        ]
    )]
    #[Route('/aggregate-statistics', name: 'get_aggregate_statistics', methods: ['POST'])]
    public function getAggregateStatistics(int $accountId, Request $request): JsonResponse
    {
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);

        $categoryIds = $data['categoryIds'] ?? [];
        if (empty($categoryIds) || !is_array($categoryIds)) {
            return $this->json(['error' => 'categoryIds is verplicht en mag niet leeg zijn'], 400);
        }

        $period = $data['period'] ?? '1y';
        $allowedPeriods = ['6m', '1y', '2y', '3y', 'all'];
        if (!in_array($period, $allowedPeriods, true)) {
            return $this->json(['error' => 'Ongeldige period. Toegestaan: ' . implode(', ', $allowedPeriods)], 400);
        }

        $includeCurrentMonth = (bool) ($data['includeCurrentMonth'] ?? false);
        $includeBreakdown = (bool) ($data['includeBreakdown'] ?? false);

        $statistics = $this->budgetInsightsService->computeCategoryStatistics($categoryIds, $period, $includeCurrentMonth, $includeBreakdown);

        return $this->json($statistics);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/categories/{id}/history',
        summary: 'Haal historische data op voor een specifieke categorie per maand',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 5)
            ),
            new OA\Parameter(
                name: 'months',
                description: 'Aantal maanden terug (optioneel, standaard = alles)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 12)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Historische data per maand voor de categorie',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'category',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'name', type: 'string', example: 'Boodschappen'),
                                new OA\Property(property: 'color', type: 'string', example: '#4CAF50'),
                                new OA\Property(property: 'icon', type: 'string', example: 'shopping-cart')
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'history',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'month', type: 'string', example: '2025-01'),
                                    new OA\Property(property: 'total', type: 'string', example: '450.50'),
                                    new OA\Property(property: 'transactionCount', type: 'integer', example: 12)
                                ]
                            )
                        ),
                        new OA\Property(property: 'totalAmount', type: 'string', example: '5406.00'),
                        new OA\Property(property: 'averagePerMonth', type: 'string', example: '450.50')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Categorie of account niet gevonden')
        ]
    )]
    #[Route('/{id}/history', name: 'get_category_history', methods: ['GET'])]
    public function getCategoryHistory(
        int $accountId,
        int $id,
        Request $request
    ): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $months = $request->query->get('months');
        $monthLimit = $months !== null ? (int)$months : null;

        $history = $this->categoryService->getCategoryHistory($accountId, $id, $monthLimit);

        return $this->json($history, 200);
    }
}
