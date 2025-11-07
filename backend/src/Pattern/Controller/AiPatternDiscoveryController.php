<?php

namespace App\Pattern\Controller;

use App\Account\Repository\AccountRepository;
use App\Category\Service\CategoryService;
use App\Enum\AiPatternSuggestionStatus;
use App\Mapper\PayloadMapper;
use App\Pattern\DTO\AcceptPatternSuggestionDTO;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\Mapper\PatternMapper;
use App\Pattern\Repository\AiPatternSuggestionRepository;
use App\Pattern\Repository\PatternRepository;
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
        private readonly ValidatorInterface $validator,
        private readonly AiPatternSuggestionRepository $suggestionRepository,
        private readonly PatternRepository $patternRepository,
        private readonly PatternMapper $patternMapper,
        private readonly AccountRepository $accountRepository
    ) {
    }

    /**
     * Verify that the authenticated user owns the specified account
     */
    private function verifyAccountOwnership(int $accountId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null;
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
                                new OA\Property(property: 'descriptionPattern', type: 'string', nullable: true, example: 'ALBERT HEIJN'),
                                new OA\Property(property: 'notesPattern', type: 'string', nullable: true, example: null),
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
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            // Haal alle ongecategoriseerde transacties op
            $transactions = $this->transactionRepository->findUncategorizedByAccount($accountId);

            if (empty($transactions)) {
                return new JsonResponse([
                    'patterns' => [],
                    'message' => 'Geen ongecategoriseerde transacties gevonden'
                ]);
            }

            // AI analyseert maximaal 200 transacties
            $analyzedCount = min(count($transactions), 200);

            // Gebruik AI om patronen te ontdekken
            $suggestions = $this->aiPatternDiscoveryService->discoverPatterns($transactions, $accountId);

            // Converteer naar array voor JSON response
            $patternsArray = array_map(function ($suggestion) {
                return [
                    'descriptionPattern' => $suggestion->descriptionPattern,
                    'notesPattern' => $suggestion->notesPattern,
                    'suggestedCategoryName' => $suggestion->suggestedCategoryName,
                    'existingCategoryId' => $suggestion->existingCategoryId,
                    'matchCount' => $suggestion->matchCount,
                    'exampleTransactions' => $suggestion->exampleTransactions,
                    'confidence' => $suggestion->confidence,
                    'reasoning' => $suggestion->reasoning,
                    'previouslyDiscovered' => $suggestion->previouslyDiscovered
                ];
            }, $suggestions);

            return new JsonResponse([
                'patterns' => $patternsArray,
                'analyzedCount' => $analyzedCount
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
                    new OA\Property(property: 'descriptionPattern', type: 'string', nullable: true, example: 'ALBERT HEIJN'),
                    new OA\Property(property: 'notesPattern', type: 'string', nullable: true, example: null),
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
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

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
            // Generate pattern hash to find the suggestion
            $patternHash = $this->generatePatternHash(
                $accountId,
                $dto->descriptionPattern,
                $dto->notesPattern
            );

            // Find the AI suggestion in database
            $aiSuggestion = $this->suggestionRepository->findByPatternHash($accountId, $patternHash);

            // Determine if pattern was altered
            $wasAltered = false;
            if ($aiSuggestion) {
                $wasAltered = ($aiSuggestion->getDescriptionPattern() !== $dto->descriptionPattern)
                    || ($aiSuggestion->getNotesPattern() !== $dto->notesPattern)
                    || ($aiSuggestion->getSuggestedCategoryName() !== $dto->categoryName);
            }

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
            $createPatternDTO->description = $dto->descriptionPattern;
            $createPatternDTO->matchTypeDescription = $dto->descriptionPattern ? 'LIKE' : null;
            $createPatternDTO->notes = $dto->notesPattern;
            $createPatternDTO->matchTypeNotes = $dto->notesPattern ? 'LIKE' : null;
            $createPatternDTO->categoryId = $categoryId;
            $createPatternDTO->strict = false;

            $patternDTO = $this->patternService->createFromDTO($createPatternDTO);

            // Retrieve the Pattern entity by hash (PatternService returns DTO, but we need the entity)
            $patternHash = $this->patternMapper->generateHash(
                $accountId,
                $dto->descriptionPattern,
                $dto->notesPattern,
                $categoryId,
                null // savingsAccountId
            );
            $patternEntity = $this->patternRepository->findByHash($patternHash);

            // Update AI suggestion status
            if ($aiSuggestion) {
                $aiSuggestion->setStatus(
                    $wasAltered ? AiPatternSuggestionStatus::ACCEPTED_ALTERED : AiPatternSuggestionStatus::ACCEPTED
                );
                $aiSuggestion->setCreatedPattern($patternEntity);
                $aiSuggestion->setProcessedAt(new \DateTimeImmutable());

                // Store what user actually chose (for feedback learning)
                $aiSuggestion->setAcceptedDescriptionPattern($dto->descriptionPattern);
                $aiSuggestion->setAcceptedNotesPattern($dto->notesPattern);
                $aiSuggestion->setAcceptedCategoryName($dto->categoryName);

                $this->suggestionRepository->save($aiSuggestion);
            }

            return new JsonResponse([
                'message' => 'Patroon succesvol geaccepteerd en aangemaakt',
                'pattern' => $patternDTO,
                'appliedToTransactions' => 0, // Pattern is automatically applied by createFromDTO
                'wasAltered' => $wasAltered
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Accepteren van patroon mislukt: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/discover/reject', name: 'reject_pattern_suggestion', methods: ['POST'])]
    #[OA\Post(
        summary: 'Wijs een AI-voorgesteld patroon af',
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
                    new OA\Property(property: 'descriptionPattern', type: 'string', nullable: true, example: 'ALBERT HEIJN'),
                    new OA\Property(property: 'notesPattern', type: 'string', nullable: true, example: null)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Patroon succesvol afgewezen',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string')
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Patroon niet gevonden'),
            new OA\Response(response: 400, description: 'Validatiefout')
        ]
    )]
    public function rejectSuggestion(int $accountId, Request $request): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Ongeldige JSON-invoer');
        }

        $descriptionPattern = $data['descriptionPattern'] ?? null;
        $notesPattern = $data['notesPattern'] ?? null;

        if (!$descriptionPattern && !$notesPattern) {
            throw new BadRequestHttpException('Ten minste één patroon (description of notes) is vereist');
        }

        try {
            // Generate pattern hash
            $patternHash = $this->generatePatternHash($accountId, $descriptionPattern, $notesPattern);

            // Find the AI suggestion
            $aiSuggestion = $this->suggestionRepository->findByPatternHash($accountId, $patternHash);

            if (!$aiSuggestion) {
                return new JsonResponse([
                    'error' => 'Patroon suggestie niet gevonden'
                ], Response::HTTP_NOT_FOUND);
            }

            // Mark as rejected
            $aiSuggestion->setStatus(AiPatternSuggestionStatus::REJECTED);
            $aiSuggestion->setProcessedAt(new \DateTimeImmutable());
            $this->suggestionRepository->save($aiSuggestion);

            return new JsonResponse([
                'message' => 'Patroon succesvol afgewezen'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Afwijzen van patroon mislukt: ' . $e->getMessage()
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

    private function generatePatternHash(int $accountId, ?string $descriptionPattern, ?string $notesPattern): string
    {
        $data = implode('|', [
            $accountId,
            $descriptionPattern ?? '',
            $notesPattern ?? ''
        ]);

        return hash('sha256', $data);
    }
}
