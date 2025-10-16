<?php

namespace App\Pattern\Controller;

use App\Category\Service\CategoryService;
use App\Mapper\PayloadMapper;
use App\Pattern\DTO\AcceptPatternSuggestionDTO;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\Service\AiPatternDiscoveryService;
use App\Pattern\Service\PatternService;
use App\Transaction\Repository\TransactionRepository;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(
    name: 'AI Pattern Discovery',
    description: 'AI-powered pattern discovery voor automatische categorisering'
)]
#[Route('/api/account/{accountId}/patterns')]
class AiPatternDiscoveryController extends AbstractController
{
    public function __construct(
        private readonly AiPatternDiscoveryService $aiPatternDiscoveryService,
        private readonly TransactionRepository $transactionRepository,
        private readonly PatternService $patternService,
        private readonly CategoryService $categoryService,
        private readonly PayloadMapper $payloadMapper,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/discover', name: 'discover_patterns', methods: ['POST'])]
    #[OA\Post(
        summary: 'Ontdek patronen in ongecategoriseerde transacties met AI',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Patronen succesvol ontdekt',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'patterns', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'patternString', type: 'string', example: 'ALBERT HEIJN*'),
                                new OA\Property(property: 'suggestedCategoryName', type: 'string', example: 'Boodschappen'),
                                new OA\Property(property: 'existingCategoryId', type: 'integer', nullable: true),
                                new OA\Property(property: 'matchCount', type: 'integer', example: 25),
                                new OA\Property(property: 'exampleTransactions', type: 'array', items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer'),
                                        new OA\Property(property: 'description', type: 'string'),
                                        new OA\Property(property: 'amount', type: 'number'),
                                        new OA\Property(property: 'date', type: 'string', format: 'date')
                                    ]
                                )),
                                new OA\Property(property: 'confidence', type: 'number', example: 0.95),
                                new OA\Property(property: 'reasoning', type: 'string', example: 'Supermarkt aankopen')
                            ]
                        ))
                    ]
                )
            ),
            new OA\Response(response: 500, description: 'AI service fout')
        ]
    )]
    public function discoverPatterns(int $accountId): JsonResponse
    {
        try {
            // Haal alle ongecategoriseerde transacties op
            $transactions = $this->transactionRepository->findUncategorizedByAccount($accountId);

            if (empty($transactions)) {
                return new JsonResponse([
                    'patterns' => [],
                    'message' => 'Geen ongecategoriseerde transacties gevonden'
                ]);
            }

            // Gebruik AI om patronen te ontdekken
            $suggestions = $this->aiPatternDiscoveryService->discoverPatterns($transactions);

            // Converteer naar array voor JSON response
            $patternsArray = array_map(function ($suggestion) {
                return [
                    'patternString' => $suggestion->patternString,
                    'suggestedCategoryName' => $suggestion->suggestedCategoryName,
                    'existingCategoryId' => $suggestion->existingCategoryId,
                    'matchCount' => $suggestion->matchCount,
                    'exampleTransactions' => $suggestion->exampleTransactions,
                    'confidence' => $suggestion->confidence,
                    'reasoning' => $suggestion->reasoning
                ];
            }, $suggestions);

            return new JsonResponse([
                'patterns' => $patternsArray,
                'totalUncategorized' => count($transactions)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Patroonontdekking mislukt: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/discover/accept', name: 'accept_pattern_suggestion', methods: ['POST'])]
    #[OA\Post(
        summary: 'Accepteer een AI-voorgesteld patroon en maak categorie + pattern aan',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'patternString', type: 'string', example: 'ALBERT HEIJN*'),
                    new OA\Property(property: 'categoryName', type: 'string', example: 'Boodschappen'),
                    new OA\Property(property: 'categoryId', type: 'integer', nullable: true, description: 'Gebruik bestaande categorie (optioneel)'),
                    new OA\Property(property: 'categoryColor', type: 'string', nullable: true, example: '#4ade80')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Patroon succesvol geaccepteerd en aangemaakt',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'category', type: 'object'),
                        new OA\Property(property: 'pattern', type: 'object'),
                        new OA\Property(property: 'appliedToTransactions', type: 'integer')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validatiefout')
        ]
    )]
    public function acceptSuggestion(int $accountId, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Ongeldige JSON-invoer');
        }

        try {
            $dto = $this->payloadMapper->map($data, new AcceptPatternSuggestionDTO());
        } catch (\Exception $e) {
            throw new BadRequestHttpException('Validatiefout: ' . $e->getMessage());
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        try {
            // Check of we een bestaande categorie gebruiken of een nieuwe maken
            $categoryId = $dto->categoryId;

            if ($categoryId === null) {
                // Maak nieuwe categorie aan
                $category = $this->categoryService->create($accountId, [
                    'name' => $dto->categoryName,
                    'color' => $dto->categoryColor ?? $this->generateRandomColor()
                ]);
                $categoryId = $category->getId();
            }

            // Maak pattern aan
            $createPatternDTO = new CreatePatternDTO();
            $createPatternDTO->accountId = $accountId;
            $createPatternDTO->description = $dto->patternString;
            $createPatternDTO->categoryId = $categoryId;
            $createPatternDTO->strict = false;

            $pattern = $this->patternService->createFromDTO($createPatternDTO);

            return new JsonResponse([
                'message' => 'Patroon succesvol geaccepteerd en aangemaakt',
                'pattern' => $pattern,
                'appliedToTransactions' => 0 // Pattern is automatically applied by createFromDTO
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Accepteren van patroon mislukt: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function generateRandomColor(): string
    {
        $colors = [
            '#ef4444', '#f97316', '#f59e0b', '#eab308', '#84cc16',
            '#22c55e', '#10b981', '#14b8a6', '#06b6d4', '#0ea5e9',
            '#3b82f6', '#6366f1', '#8b5cf6', '#a855f7', '#d946ef',
            '#ec4899', '#f43f5e'
        ];
        return $colors[array_rand($colors)];
    }
}
