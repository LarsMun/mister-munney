<?php

namespace App\Category\Controller;

use App\Category\DTO\CategoryDTO;
use App\Category\DTO\CategoryWithTransactionsDTO;
use App\Category\Mapper\CategoryMapper;
use App\Category\Service\CategoryService;

use App\Transaction\Service\TransactionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
    private CategoryService $categoryService;
    private CategoryMapper $categoryMapper;
    private TransactionService $transactionService;
    public function __construct(
        CategoryService $categoryService,
        CategoryMapper $categoryMapper,
        TransactionService $transactionService,
    )
    {
        $this->categoryService = $categoryService;
        $this->categoryMapper = $categoryMapper;
        $this->transactionService = $transactionService;
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
        $categoryDto = $this->categoryService->getWithTransactions($id, $accountId);
        return $this->json($categoryDto);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/categories',
        summary: 'Maak een nieuwe categorie aan',
        requestBody: new OA\RequestBody(
            description: 'Categoriegegevens (name en transactionType zijn verplicht, icon en color zijn optioneel)',
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'transactionType'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Boodschappen'),
                    new OA\Property(property: 'transactionType', type: 'string', enum: ['debit', 'credit'], example: 'debit'),
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
        $data = json_decode($request->getContent(), true);
        $category = $this->categoryService->create($accountId, $data);
        $dto = $this->categoryMapper->toDto($category);

        return $this->json($dto, 201);
    }

    #[OA\Put(
        path: '/api/account/{accountId}/categories/{id}',
        summary: 'Werk een categorie bij',
        requestBody: new OA\RequestBody(
            description: 'Bijgewerkte categorienaam en optionele eigenschappen (transactionType kan niet gewijzigd worden)',
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
        $data = json_decode($request->getContent(), true);
        $category = $this->categoryService->update($id, $accountId, $data);
        $dto = $this->categoryMapper->toDto($category);

        return $this->json($dto);
    }

    #[OA\Delete(
        path: '/api/account/{accountId}/categories/{id}',
        summary: 'Verwijder een categorie',
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
            new OA\Response(response: 404, description: 'Categorie niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'delete_category', methods: ['DELETE'])]
    public function delete(int $accountId, int $id): JsonResponse
    {
        $this->categoryService->delete($id, $accountId);
        return $this->json(null, 204);
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
        $months = $request->query->get('months', 'all');

        $statistics = $this->categoryService->getCategoryStatistics($accountId, $months);

        return $this->json($statistics, 200);
    }
}
