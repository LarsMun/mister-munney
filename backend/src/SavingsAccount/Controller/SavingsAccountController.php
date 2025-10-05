<?php

namespace App\SavingsAccount\Controller;

use App\Entity\Account;
use App\Mapper\PayloadMapper;
use App\SavingsAccount\DTO\CreateSavingsAccountDTO;
use App\SavingsAccount\DTO\UpdateSavingsAccountDTO;
use App\SavingsAccount\Mapper\SavingsAccountMapper;
use App\SavingsAccount\DTO\SavingsAccountDTO;
use App\SavingsAccount\Service\SavingsAccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Savings Accounts',
    description: 'Beheer van spaarrekeningen: aanmaken, bewerken, verwijderen en automatisch toewijzen van transacties op basis van patronen.'
)]
#[Route('/api/account/{accountId}/savings-accounts')]
class SavingsAccountController extends AbstractController
{
    private SavingsAccountService $savingsAccountService;
    private SavingsAccountMapper $savingsAccountMapper;
    private PayloadMapper $payloadMapper;
    private ValidatorInterface $validator;

    public function __construct
    (
        SavingsAccountService $savingsAccountService,
        SavingsAccountMapper $savingsAccountMapper,
        PayloadMapper $payloadMapper,
        ValidatorInterface $validator
    )
    {
        $this->savingsAccountService = $savingsAccountService;
        $this->savingsAccountMapper = $savingsAccountMapper;
        $this->payloadMapper = $payloadMapper;
        $this->validator = $validator;
    }

    #[OA\Get(
        path: '/api/account/{accountId}/savings-accounts',
        summary: 'Toon alle spaarrekeningen van een account',
        tags: ['Savings Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvan de spaarrekeningen worden opgehaald',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van spaarrekeningen',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: SavingsAccountDTO::class))
                )
            )
        ]
    )]
    #[Route('', name: 'savings_accounts_list', methods: ['GET'])]
    public function list(int $accountId): JsonResponse
    {
        $savingsAccounts = $this->savingsAccountService->getAllSavingsAccounts($accountId);
        $dto = $this->savingsAccountMapper->toDtoList($savingsAccounts);
        return $this->json($dto);
    }

    #[OA\Get(
        path: '/api/account/{accountId}/savings-accounts/{id}',
        summary: 'Toon details van een specifieke spaarrekening',
        tags: ['Savings Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'account',
                description: 'ID van het account waarin gezocht moet worden',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'id',
                description: 'ID van de spaarrekening',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)
            )
        ],
        responses: [
            new OA\Response(response: 404, description: 'Spaarrekening niet gevonden of hoort niet bij dit account')
        ]
    )]
    #[Route('/{id}', name: 'savings_account_show', methods: ['GET'])]
    public function show(int $id, Account $account): JsonResponse
    {
        $savingsAccount = $this->savingsAccountService->getSavingsAccountById($id);

        if (!$savingsAccount) {
            return $this->json(['error' => 'Spaarrekening niet gevonden'], Response::HTTP_NOT_FOUND);
        }

        if ($savingsAccount->getAccount()->getId() !== $account->getId()) {
            return $this->json([
                'error' => 'Spaarrekening hoort niet bij dit account',
                'expected' => $account->getId(),
                'actual' => $savingsAccount->getAccount()->getId() . " " . $id,
            ], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->savingsAccountMapper->toWithPatternsAndAccountDto($savingsAccount);
        return $this->json($dto);
    }

    #[OA\Post(
        path: '/api/account/{accountId}/savings-accounts',
        summary: 'Maak een nieuwe spaarrekening aan',
        requestBody: new OA\RequestBody(
            description: 'Gegevens van de nieuwe spaarrekening',
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        description: 'Naam van de spaarrekening',
                        type: 'string',
                        example: 'Vakantie naar Amerika'
                    ),
                    new OA\Property(
                        property: 'targetAmount',
                        description: 'Streefbedrag voor de spaarrekening (optioneel)',
                        type: 'number',
                        format: 'float',
                        example: 1500.00
                    ),
                    new OA\Property(
                        property: 'color',
                        description: 'Kleur van de spaarrekening (bijv. in UI)',
                        type: 'string',
                        example: '#FFCC00'
                    )
                ]
            )
        ),
        tags: ['Savings Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van de gekoppelde betaalrekening',
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
                description: 'Spaarrekening succesvol aangemaakt',
                content: new OA\JsonContent(ref: new Model(type: SavingsAccountDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 409, description: 'Spaarrekening bestaat al'),
            new OA\Response(response: 500, description: 'Interne serverfout')
        ]
    )]
    #[Route('', name: 'savings_account_create', methods: ['POST'])]
    public function create(
        int $accountId,
        Request $request
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $dto = $this->payloadMapper->map($data, new CreateSavingsAccountDTO(), true);

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new BadRequestHttpException((string) $errors);
        }

        $entity = $this->savingsAccountService->createFromDto($dto, $accountId);

        return $this->json(
            $this->savingsAccountMapper->toSimpleDto($entity),
            Response::HTTP_CREATED
        );
    }

    #[OA\Put(
        path: '/api/account/{accountId}/savings-accounts/{id}',
        summary: 'Werk een bestaande spaarrekening bij',
        requestBody: new OA\RequestBody(
            description: 'Nieuwe waarden voor de spaarrekening (alle velden optioneel)',
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateSavingsAccountDTO::class))
        ),
        tags: ['Savings Accounts'],
        parameters: [
            new OA\Parameter
            (
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema
                (
                    type: 'integer', maximum: 2147483647, minimum: 1, example: 1
                )
            ),
            new OA\Parameter
            (
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema
                (
                    type: 'integer', maximum: 2147483647, minimum: 1, example: 1
                )
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Spaarrekening bijgewerkt', content: new OA\JsonContent(ref: new Model(type: SavingsAccountDTO::class))),
            new OA\Response(response: 400, description: 'Ongeldige invoer'),
            new OA\Response(response: 404, description: 'Spaarrekening niet gevonden'),
            new OA\Response(response: 409, description: 'Conflict'),
            new OA\Response(response: 500, description: 'Interne serverfout')
        ]
    )]
    #[Route('/{id}', name: 'savings_account_update', methods: ['PUT'])]
    public function update(
        string $accountId,
        string $id,
        Request $request
    ): JsonResponse {
        if (!ctype_digit($accountId) || !ctype_digit($id)) {
            throw new BadRequestHttpException('Ongeldig ID opgegeven.');
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            throw new BadRequestHttpException('Request body moet geldig JSON zijn.');
        }

        $dto = $this->savingsAccountMapper->fromUpdatePayload($payload);

        $updatedEntity = $this->savingsAccountService->updateFromDto(
            (int)$id,
            (int)$accountId,
            $dto
        );

        return $this->json($this->savingsAccountMapper->toSimpleDto($updatedEntity));
    }

    #[OA\Delete(
        path: '/api/account/{accountId}/savings-accounts/{id}',
        summary: 'Verwijder een spaarrekening',
        tags: ['Savings Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID van de spaarrekening die je wilt verwijderen',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 42)
            )
        ],
        responses: [
            new OA\Response(response: 204, description: 'Spaarrekening succesvol verwijderd'),
            new OA\Response(response: 404, description: 'Spaarrekening niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'savings_account_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $savingsAccount = $this->savingsAccountService->getSavingsAccountById($id);
        if (!$savingsAccount) {
            return $this->json(['error' => 'Spaarrekening niet gevonden'], Response::HTTP_NOT_FOUND);
        }

        $this->savingsAccountService->deleteSavingsAccount($savingsAccount);
        return $this->json(['message' => 'Spaarrekening verwijderd'], Response::HTTP_NO_CONTENT);
    }
}