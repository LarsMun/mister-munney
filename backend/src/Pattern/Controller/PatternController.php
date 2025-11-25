<?php

namespace App\Pattern\Controller;

use App\Account\Repository\AccountRepository;
use App\Mapper\PayloadMapper;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\DTO\PatternDTO;
use App\Pattern\DTO\UpdatePatternDTO;
use App\Pattern\Service\PatternService;
use App\Shared\Controller\AccountOwnershipTrait;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\PropertyAccess\Exception\InvalidPropertyPathException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Nelmio\ApiDocBundle\Attribute\Model;

#[OA\Tag(
    name: 'Patterns',
    description: 'Beheer van patterns'
)]
#[Route('/api/account/{accountId}/patterns')]
class PatternController extends AbstractController
{
    use AccountOwnershipTrait;

    private PatternService $patternService;
    private PayloadMapper $payloadMapper;
    private ValidatorInterface $validator;
    private AccountRepository $accountRepository;

    public function __construct(
        PatternService $patternService,
        PayloadMapper $payloadMapper,
        ValidatorInterface $validator,
        AccountRepository $accountRepository
    )
    {
        $this->patternService = $patternService;
        $this->payloadMapper = $payloadMapper;
        $this->validator = $validator;
        $this->accountRepository = $accountRepository;
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    #[Route('', name: 'create_pattern', methods: ['POST'])]
    #[OA\Post(
        summary: 'Maak een nieuw pattern aan',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreatePatternDTO::class))
        ),
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvoor het pattern wordt aangemaakt',
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
                response: 201,
                description: 'Pattern succesvol aangemaakt',
                content: new OA\JsonContent(ref: new Model(type: PatternDTO::class))
            ),
            new OA\Response(response: 400, description: 'Validatiefout')
        ]
    )]
    public function create(int $accountId, Request $request): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Ongeldige JSON-invoer');
        }

        try {
            $dto = $this->payloadMapper->map($data, new CreatePatternDTO(), true);
        } catch (InvalidPropertyPathException $e) {
            throw new BadRequestHttpException('Ongeldige invoervelden gedetecteerd: ' . $e->getMessage());
        }

        $dto->accountId = $accountId;

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $patternDTO = $this->patternService->createFromDTO($dto);

        return $this->json($patternDTO, 201);
    }

    #[Route('/{patternId}', name: 'update_pattern', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Wijzig een bestaand pattern',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdatePatternDTO::class))
        ),
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'patternId',
                description: 'ID van het pattern dat je wil wijzigen',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 12)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pattern succesvol bijgewerkt',
                content: new OA\JsonContent(ref: new Model(type: PatternDTO::class))
            ),
            new OA\Response(response: 400, description: 'Validatiefout'),
            new OA\Response(response: 404, description: 'Pattern niet gevonden')
        ]
    )]
    public function update(
        int $accountId,
        int $patternId,
        Request $request
    ): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new BadRequestHttpException('Ongeldige JSON-invoer');
        }

        try {
            $dto = $this->payloadMapper->map($data, new UpdatePatternDTO(), true);
        } catch (InvalidPropertyPathException $e) {
            throw new BadRequestHttpException('Ongeldige invoervelden gedetecteerd: ' . $e->getMessage());
        }

        $dto->accountId = $accountId;

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $patternDto = $this->patternService->updateFromDTO($patternId, $dto);

        return $this->json($patternDto);
    }

    #[Route('/without-category', name: 'delete_patterns_without_category', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Verwijder alle patterns zonder categorie',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Patterns verwijderd',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'deletedCount', type: 'integer', example: 5),
                        new OA\Property(property: 'message', type: 'string', example: '5 patterns zonder categorie verwijderd')
                    ]
                )
            )
        ]
    )]
    public function deleteWithoutCategory(int $accountId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        $count = $this->patternService->deleteWithoutCategory($accountId);

        return $this->json([
            'deletedCount' => $count,
            'message' => "$count patterns zonder categorie verwijderd"
        ]);
    }

    #[Route('/{patternId}', name: 'delete_pattern', methods: ['DELETE'])]
    #[OA\Delete(
        summary: 'Verwijder een pattern',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'patternId',
                description: 'ID van het te verwijderen pattern',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 12)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Pattern verwijderd'),
            new OA\Response(response: 403, description: 'Pattern hoort niet bij account'),
            new OA\Response(response: 404, description: 'Pattern niet gevonden'),
        ]
    )]
    public function delete(string $accountId, string $patternId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership((int)$accountId)) {
            return $error;
        }

        $this->patternService->deletePattern((int)$accountId, (int)$patternId);
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('', name: 'get_patterns_by_account', methods: ['GET'])]
    #[OA\Get(
        summary: 'Alle patterns voor een account',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van patterns',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PatternDTO::class))
                )
            )
        ]
    )]
    public function listByAccount(int $accountId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        return $this->json($this->patternService->getByAccount($accountId));
    }

    #[Route('/category/{categoryId}', name: 'get_patterns_by_category', methods: ['GET'])]
    #[OA\Get(
        summary: 'Alle patterns voor een categorie',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'categoryId',
                description: 'ID van de categorie',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 3)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van patterns',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: PatternDTO::class))
                )
            )
        ]
    )]
    public function listByCategory(int $accountId, int $categoryId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        return $this->json($this->patternService->getByCategory($accountId, $categoryId));
    }

    #[Route('/{patternId}', name: 'get_pattern_by_id', methods: ['GET'])]
    #[OA\Get(
        summary: 'Geef één pattern op basis van ID',
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'patternId',
                description: 'ID van het pattern',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pattern details',
                content: new OA\JsonContent(ref: new Model(type: PatternDTO::class))
            ),
            new OA\Response(response: 403, description: 'Pattern hoort niet bij account'),
            new OA\Response(response: 404, description: 'Pattern niet gevonden')
        ]
    )]
    public function getById(int $accountId, int $patternId): JsonResponse
    {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        return $this->json($this->patternService->getById($accountId, $patternId));
    }
}