<?php

namespace App\Budget\Controller;

use App\Budget\DTO\BudgetVersionDTO;
use App\Budget\DTO\CreateBudgetVersionDTO;
use App\Budget\DTO\UpdateBudgetVersionDTO;
use App\Budget\Mapper\BudgetMapper;
use App\Budget\Service\BudgetVersionService;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/account/{accountId}/budget/{budgetId}/version')]
#[OA\Tag(
    name: 'Budgetversies',
    description: 'Beheer van budgetversies'
)]
class BudgetVersionController extends AbstractController
{
    private BudgetVersionService $budgetVersionService;
    private BudgetMapper $budgetMapper;
    private SerializerInterface $serializer;
    private ValidatorInterface $validator;

    public function __construct(
        BudgetVersionService $budgetVersionService,
        BudgetMapper $budgetMapper,
        SerializerInterface $serializer,
        ValidatorInterface $validator
    ) {
        $this->budgetVersionService = $budgetVersionService;
        $this->budgetMapper = $budgetMapper;
        $this->serializer = $serializer;
        $this->validator = $validator;
    }

    #[Route('/create', name: 'create_budget_version', methods: ['POST'])]
    #[OA\Post(
        path: '/api/account/{accountId}/budget/{budgetId}/version/create',
        description: 'Maakt een nieuwe budget versie aan',
        summary: 'Maak nieuwe budget versie',
        requestBody: new OA\RequestBody(
            description: 'Budget versie gegevens',
            required: true,
            content: new OA\JsonContent(
                required: ['monthlyAmount', 'effectiveFromMonth'],
                properties: [
                    new OA\Property(property: 'monthlyAmount', type: 'number', format: 'float', example: 2500.50),
                    new OA\Property(property: 'effectiveFromMonth', type: 'string', pattern: '^\d{4}-\d{2}$', example: '2024-12'),
                    new OA\Property(property: 'effectiveUntilMonth', type: 'string', pattern: '^\d{4}-\d{2}$', example: '2025-12', nullable: true),
                    new OA\Property(property: 'changeReason', type: 'string', example: 'Salaris verhoging', nullable: true)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Budget versie succesvol aangemaakt',
                content: new OA\JsonContent(ref: new Model(type: BudgetVersionDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 404, description: 'Budget of account niet gevonden')
        ]
    )]
    public function createVersion(string $accountId, string $budgetId, Request $request): JsonResponse
    {
        // Deserialize and validate
        $createDTO = $this->serializer->deserialize(
            $request->getContent(),
            CreateBudgetVersionDTO::class,
            'json'
        );

        $errors = $this->validator->validate($createDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Create version
        $version = $this->budgetVersionService->createVersion((int)$accountId, (int)$budgetId, $createDTO);

        // Map to DTO
        $versionDTO = $this->budgetMapper->versionToDto($version);

        return $this->json($versionDTO, Response::HTTP_CREATED);
    }

    #[Route('/{versionId}', name: 'update_budget_version', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/account/{accountId}/budget/{budgetId}/version/{versionId}',
        description: 'Update een bestaande budget versie. Alle velden zijn optioneel - alleen meegestuurde velden worden bijgewerkt.',
        summary: 'Update budget versie',
        requestBody: new OA\RequestBody(
            description: 'Budget versie update gegevens (alle velden optioneel)',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'monthlyAmount',
                        description: 'Nieuw maandelijks bedrag',
                        type: 'number',
                        format: 'float',
                        example: 2750.00,
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'effectiveFromMonth',
                        description: 'Nieuwe startdatum in YYYY-MM formaat',
                        type: 'string',
                        pattern: '^\d{4}-\d{2}$',
                        example: '2025-01',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'effectiveUntilMonth',
                        description: 'Nieuwe einddatum in YYYY-MM formaat (null = oneindig)',
                        type: 'string',
                        pattern: '^\d{4}-\d{2}$',
                        example: '2025-12',
                        nullable: true
                    ),
                    new OA\Property(
                        property: 'changeReason',
                        description: 'Reden voor wijziging',
                        type: 'string',
                        example: 'Budget aangepast na evaluatie',
                        nullable: true
                    )
                ]
            )
        ),
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
                name: 'versionId',
                description: 'ID van de budget versie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 12)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Budget versie succesvol bijgewerkt',
                content: new OA\JsonContent(ref: new Model(type: BudgetVersionDTO::class))
            ),
            new OA\Response(
                response: 400,
                description: 'Ongeldige invoer of overlappende datums'
            ),
            new OA\Response(
                response: 404,
                description: 'Versie, budget of account niet gevonden'
            )
        ]
    )]
    public function updateVersion(string $accountId, string $budgetId, string $versionId, Request $request): JsonResponse
    {
        $updateDTO = $this->serializer->deserialize(
            $request->getContent(),
            UpdateBudgetVersionDTO::class,
            'json'
        );

        $errors = $this->validator->validate($updateDTO);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        $version = $this->budgetVersionService->updateVersion(
            (int)$accountId,
            (int)$budgetId,
            (int)$versionId,
            $updateDTO
        );

        $versionDTO = $this->budgetMapper->versionToDto($version);
        return $this->json($versionDTO);
    }

    #[Route('/{versionId}', name: 'delete_budget_version', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/account/{accountId}/budget/{budgetId}/version/{versionId}',
        description: 'Verwijder een budget versie',
        summary: 'Verwijder budget versie',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'budgetId',
                description: 'ID van het budget',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            ),
            new OA\Parameter(
                name: 'versionId',
                description: 'ID van de versie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 204,
                description: 'Budget versie succesvol verwijderd'
            ),
            new OA\Response(response: 404, description: 'Versie, budget of account niet gevonden'),
            new OA\Response(response: 400, description: 'Kan laatste versie niet verwijderen')
        ]
    )]
    public function deleteVersion(string $accountId, string $budgetId, string $versionId): JsonResponse
    {
        $this->budgetVersionService->deleteVersion((int)$accountId, (int)$budgetId, (int)$versionId);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

}