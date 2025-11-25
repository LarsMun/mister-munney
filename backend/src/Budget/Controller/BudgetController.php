<?php

namespace App\Budget\Controller;

use App\Account\Repository\AccountRepository;
use App\Budget\DTO\AssignCategoriesDTO;
use App\Budget\DTO\BudgetDTO;
use App\Budget\DTO\BudgetSummaryDTO;
use App\Budget\DTO\CreateBudgetDTO;
use App\Budget\DTO\UpdateBudgetDTO;
use App\Budget\Service\BudgetService;
use App\Budget\Mapper\BudgetMapper;
use App\Entity\Budget;
use App\Shared\Controller\AccountOwnershipTrait;
use Exception;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/account/{accountId}/budget')]
#[OA\Tag(
    name: 'Budgetten',
    description: 'Beheer van budgetten'
)]
class BudgetController extends AbstractController
{
    use AccountOwnershipTrait;

    private BudgetService $budgetService;
    private BudgetMapper $budgetMapper;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;
    private AccountRepository $accountRepository;

    public function __construct(
        BudgetService $budgetService,
        BudgetMapper $budgetMapper,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        AccountRepository $accountRepository
    ) {
        $this->budgetService = $budgetService;
        $this->budgetMapper = $budgetMapper;
        $this->serializer = $serializer;
        $this->validator = $validator;
        $this->accountRepository = $accountRepository;
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    /**
     * @throws Exception
     */
    #[Route('/create', name: 'create_budget', methods: ['POST'])]
    #[OA\Post(
        path: '/api/account/{accountId}/budget/create',
        description: 'Maakt een nieuw budget aan met een eerste versie voor het opgegeven account',
        summary: 'Maak een nieuw budget aan',
        requestBody: new OA\RequestBody(
            description: 'Budgetgegevens',
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'accountId', 'budgetType', 'monthlyAmount', 'effectiveFromMonth'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Maandelijks Budget'),
                    new OA\Property(property: 'accountId', type: 'integer', example: 1),
                    new OA\Property(property: 'budgetType', type: 'string', enum: ['EXPENSE', 'INCOME'], example: 'EXPENSE'),
                    new OA\Property(property: 'monthlyAmount', type: 'number', format: 'float', example: 2500.50),
                    new OA\Property(property: 'effectiveFromMonth', type: 'string', pattern: '^\d{4}-\d{2}$', example: '2024-01'),
                    new OA\Property(property: 'changeReason', type: 'string', example: 'Initieel budget', nullable: true)
                ]
            )
        ),
        tags: ['Budgetten'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Budget succesvol aangemaakt',
                content: new OA\JsonContent(ref: new Model(type: BudgetDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 404, description: 'Account niet gevonden'),
            new OA\Response(response: 500, description: 'Server fout')
        ]
    )]
    public function createBudget(int $accountId, Request $request): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        // Deserialize request data to DTO
        $createBudgetDTO = $this->serializer->deserialize(
            $request->getContent(),
            CreateBudgetDTO::class,
            'json'
        );

        // Validate DTO
        $errors = $this->validator->validate($createBudgetDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Create budget - laat exceptions door naar ApiExceptionListener

        $budget = $this->budgetService->createBudget($createBudgetDTO);

        // Map to DTO and return
        $budgetDTO = $this->budgetMapper->toDTO($budget);

        return $this->json($budgetDTO, Response::HTTP_CREATED);
    }

    #[Route('/{budgetId}', name: 'update_budget', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/account/{accountId}/budget/{budgetId}',
        description: 'Update basis gegevens van een budget (naam). Budget versies worden via aparte endpoints beheerd.',
        summary: 'Update budget gegevens',
        requestBody: new OA\RequestBody(
            description: 'Budget update gegevens',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Aangepast Budget Naam', nullable: true),
                    new OA\Property(property: 'budgetType', type: 'string', enum: ['EXPENSE', 'INCOME'], example: 'EXPENSE', nullable: true)
                ]
            )
        ),
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Budget succesvol bijgewerkt',
                content: new OA\JsonContent(ref: new Model(type: BudgetDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 404, description: 'Budget of account niet gevonden'),
            new OA\Response(response: 500, description: 'Server fout')
        ]
    )]
    public function updateBudget(int $accountId, int $budgetId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        // Deserialize request data to DTO
        $updateBudgetDTO = $this->serializer->deserialize(
            $request->getContent(),
            UpdateBudgetDTO::class,
            'json'
        );

        // Validate DTO
        $errors = $this->validator->validate($updateBudgetDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Update budget
        $budget = $this->budgetService->updateBudget($accountId, $budgetId, $updateBudgetDTO);

        // Map to DTO and return
        $budgetDTO = $this->budgetMapper->toDTO($budget);

        return $this->json($budgetDTO);
    }

    #[Route('/{budgetId}', name: 'delete_budget', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/account/{accountId}/budget/{budgetId}',
        description: 'Verwijder een budget inclusief alle versies en categorieën',
        summary: 'Verwijder budget',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 5)
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Budget succesvol verwijderd'
            ),
            new OA\Response(
                response: 404,
                description: 'Budget of account niet gevonden'
            )
        ]
    )]
    public function deleteBudget(int $accountId, int $budgetId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $this->budgetService->deleteBudget($accountId, $budgetId);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('', name: 'list_budgets', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget',
        description: 'Haalt alle budgetten op voor het opgegeven account',
        summary: 'Toon alle budgetten voor account',
        tags: ['Budgetten'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van budgetten',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: BudgetDTO::class))
                )
            ),
            new OA\Response(response: 404, description: 'Account niet gevonden'),
            new OA\Response(response: 500, description: 'Server fout')
        ]
    )]
    public function findBudgetsByAccount(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $budgets = $this->budgetService->findBudgetsByAccount($accountId);

        $budgetDTOs = array_map(
            fn(Budget $budget) => $this->budgetMapper->toDTO($budget),
            $budgets
        );

        return $this->json($budgetDTOs);
    }

    #[Route('/month/{monthYear}', name: 'list_budgets_for_month', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget/month/{monthYear}',
        description: 'Haalt budgetten op die actief zijn in een specifieke maand',
        summary: 'Toon actieve budgetten voor specifieke maand',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'monthYear',
                description: 'Maand en jaar in YYYY-MM formaat',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', pattern: '^\d{4}-\d{2}$', example: '2024-12')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van actieve budgetten voor de maand',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: BudgetDTO::class))
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige maand format'),
            new OA\Response(response: 404, description: 'Account niet gevonden'),
            new OA\Response(response: 500, description: 'Server fout')
        ]
    )]
    public function findBudgetsForMonth(int $accountId, string $monthYear): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        // Validate monthYear format
        if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
            return $this->json([
                'error' => 'Invalid month format. Use YYYY-MM (e.g., 2024-12)'
            ], Response::HTTP_BAD_REQUEST);
        }

        $budgets = $this->budgetService->findBudgetsForMonth($accountId, $monthYear);

        $budgetDTOs = array_map(
            fn(Budget $budget) => $this->budgetMapper->toDTO($budget),
            $budgets
        );

        return $this->json($budgetDTOs);
    }

    #[Route('/{budgetId}', name: 'get_budget_details', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget/{budgetId}',
        description: 'Haalt details van een specifiek budget op inclusief alle versies en categorieën',
        summary: 'Toon budget details',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Budget details met alle versies en categorieën',
                content: new OA\JsonContent(ref: new Model(type: BudgetDTO::class))
            ),
            new OA\Response(response: 404, description: 'Budget of account niet gevonden'),
            new OA\Response(response: 500, description: 'Server fout')
        ]
    )]
    public function getBudgetDetails(int $accountId, int $budgetId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $budget = $this->budgetService->findBudgetById($accountId, $budgetId, true);

        $budgetDTO = $this->budgetMapper->toDTO($budget);

        return $this->json($budgetDTO);
    }

    #[Route('/{budgetId}/categories', name: 'assign_categories_to_budget', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/account/{accountId}/budget/{budgetId}/categories',
        description: 'Wijs categorieën toe aan een budget. Vervangt alle huidige categorieën.',
        summary: 'Wijs categorieën toe aan budget',
        requestBody: new OA\RequestBody(
            description: 'Category IDs om toe te wijzen',
            required: true,
            content: new OA\JsonContent(
                required: ['categoryIds'],
                properties: [
                    new OA\Property(
                        property: 'categoryIds',
                        type: 'array',
                        items: new OA\Items(type: 'integer'),
                        example: [1, 2, 3]
                    )
                ]
            )
        ),
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 5)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categorieën succesvol toegewezen',
                content: new OA\JsonContent(ref: new Model(type: BudgetDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 404, description: 'Budget of account niet gevonden')
        ]
    )]
    public function assignCategories(int $accountId, int $budgetId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $assignDTO = $this->serializer->deserialize(
            $request->getContent(),
            AssignCategoriesDTO::class,
            'json'
        );

        $errors = $this->validator->validate($assignDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $budget = $this->budgetService->assignCategoriesToBudget($accountId, $budgetId, $assignDTO->categoryIds);

        $budgetDTO = $this->budgetMapper->toDTO($budget);

        return $this->json($budgetDTO);
    }

    #[Route('/{budgetId}/categories/{categoryId}', name: 'remove_category_from_budget', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/account/{accountId}/budget/{budgetId}/categories/{categoryId}',
        description: 'Verwijder een categorie van een budget',
        summary: 'Verwijder categorie van budget',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 5)
            ),
            new OA\Parameter(
                name: 'categoryId',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 2)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Categorie succesvol verwijderd van budget',
                content: new OA\JsonContent(ref: new Model(type: BudgetDTO::class))
            ),
            new OA\Response(response: 404, description: 'Budget, category of account niet gevonden')
        ]
    )]
    public function removeCategory(int $accountId, int $budgetId, int $categoryId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $budget = $this->budgetService->removeCategoryFromBudget($accountId, $budgetId, $categoryId);

        $budgetDTO = $this->budgetMapper->toDTO($budget);

        return $this->json($budgetDTO);
    }

    #[Route('/summary/{monthYear}', name: 'get_budget_summaries', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget/summary/{monthYear}',
        description: 'Haalt budget summaries op voor een specifieke maand met uitgaveninformatie en trends',
        summary: 'Toon budget summaries voor maand',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'monthYear',
                description: 'Maand en jaar in YYYY-MM formaat',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', pattern: '^\d{4}-\d{2}$', example: '2024-12')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Budget summaries met uitgaven en trends',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: BudgetSummaryDTO::class))
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige maand format'),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    public function getBudgetSummaries(int $accountId, string $monthYear): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        // Validate monthYear format
        if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
            return $this->json([
                'error' => 'Invalid month format. Use YYYY-MM (e.g., 2024-12)'
            ], Response::HTTP_BAD_REQUEST);
        }

        $summaries = $this->budgetService->getBudgetSummariesForMonth($accountId, $monthYear);

        // Get uncategorized transaction stats
        $uncategorizedStats = $this->budgetService->getUncategorizedTransactionStats($accountId, $monthYear);

        return $this->json([
            'summaries' => $summaries,
            'uncategorized' => $uncategorizedStats
        ]);
    }

    #[Route('/{budgetId}/breakdown/{monthYear}', name: 'get_budget_category_breakdown', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget/{budgetId}/breakdown/{monthYear}',
        description: 'Haalt de breakdown van uitgaven per categorie op voor een specifiek budget in een maand',
        summary: 'Toon categorie breakdown voor budget',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 5)
            ),
            new OA\Parameter(
                name: 'monthYear',
                description: 'Maand en jaar in YYYY-MM formaat',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', pattern: '^\d{4}-\d{2}$', example: '2024-12')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category breakdown met bedragen en transactie counts',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'categoryId', type: 'integer', example: 1),
                            new OA\Property(property: 'categoryName', type: 'string', example: 'Supermarkt'),
                            new OA\Property(property: 'categoryColor', type: 'string', example: '#3B82F6'),
                            new OA\Property(property: 'spentAmount', type: 'number', format: 'float', example: 700.50),
                            new OA\Property(property: 'transactionCount', type: 'integer', example: 15)
                        ]
                    )
                )
            ),
            new OA\Response(response: 400, description: 'Ongeldige maand format'),
            new OA\Response(response: 404, description: 'Budget of account niet gevonden')
        ]
    )]
    public function getCategoryBreakdown(int $accountId, int $budgetId, string $monthYear): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        // Validate monthYear format
        if (!preg_match('/^\d{4}-\d{2}$/', $monthYear)) {
            return $this->json([
                'error' => 'Invalid month format. Use YYYY-MM (e.g., 2024-12)'
            ], Response::HTTP_BAD_REQUEST);
        }

        $breakdown = $this->budgetService->getCategoryBreakdownForBudget($accountId, $budgetId, $monthYear);

        return $this->json($breakdown);
    }

    #[Route('/{budgetId}/breakdown-range', name: 'get_budget_category_breakdown_range', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget/{budgetId}/breakdown-range',
        description: 'Haalt de breakdown van uitgaven per categorie op voor een specifiek budget in een datumbereik',
        summary: 'Toon categorie breakdown voor budget (datumbereik)',
        parameters: [
            new OA\Parameter(name: 'accountId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'budgetId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'startDate', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'endDate', in: 'query', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Category breakdown met bedragen en transactie counts',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'categoryId', type: 'integer'),
                            new OA\Property(property: 'categoryName', type: 'string'),
                            new OA\Property(property: 'categoryColor', type: 'string'),
                            new OA\Property(property: 'spentAmount', type: 'number'),
                            new OA\Property(property: 'transactionCount', type: 'integer')
                        ]
                    )
                )
            )
        ]
    )]
    public function getCategoryBreakdownRange(
        int $accountId,
        int $budgetId,
        Request $request
    ): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');

        if (!$startDate || !$endDate) {
            return $this->json([
                'error' => 'Both startDate and endDate are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $breakdown = $this->budgetService->getCategoryBreakdownForBudgetRange($accountId, $budgetId, $startDate, $endDate);

        return $this->json($breakdown);
    }

    #[Route('/{budgetId}/history', name: 'get_budget_history', methods: ['GET'])]
    #[OA\Get(
        path: '/api/account/{accountId}/budget/{budgetId}/history',
        summary: 'Haal historische data op voor een specifiek budget per maand',
        tags: ['Budgetten'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
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
                description: 'Historische data per maand voor het budget',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'budget',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 5),
                                new OA\Property(property: 'name', type: 'string', example: 'Vaste Lasten'),
                                new OA\Property(property: 'budgetType', type: 'string', example: 'EXPENSE'),
                                new OA\Property(
                                    property: 'categoryIds',
                                    type: 'array',
                                    items: new OA\Items(type: 'integer'),
                                    example: [1, 2, 3]
                                )
                            ],
                            type: 'object'
                        ),
                        new OA\Property(
                            property: 'history',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'month', type: 'string', example: '2025-01'),
                                    new OA\Property(property: 'total', type: 'string', example: '1250.50'),
                                    new OA\Property(property: 'transactionCount', type: 'integer', example: 45)
                                ]
                            )
                        ),
                        new OA\Property(property: 'totalAmount', type: 'string', example: '15006.00'),
                        new OA\Property(property: 'averagePerMonth', type: 'string', example: '1250.50'),
                        new OA\Property(property: 'monthCount', type: 'integer', example: 12)
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Budget of account niet gevonden')
        ]
    )]
    public function getBudgetHistory(
        int $accountId,
        int $budgetId,
        Request $request
    ): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $months = $request->query->get('months');
        $monthLimit = $months !== null ? (int)$months : null;

        $history = $this->budgetService->getBudgetHistory($accountId, $budgetId, $monthLimit);

        return $this->json($history, 200);
    }

}
