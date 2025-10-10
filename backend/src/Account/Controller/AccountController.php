<?php

namespace App\Account\Controller;

use App\Account\DTO\AccountDTO;
use App\Account\Mapper\AccountMapper;
use App\Account\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Accounts',
    description: 'Beheer van bankrekeningen en hun basisgegevens'
)]
#[Route('/api/accounts', name: 'api_accounts_')]
class AccountController extends AbstractController
{
    private AccountService $accountService;
    private AccountMapper $accountMapper;

    public function __construct(AccountService $accountService, AccountMapper $accountMapper)
    {
        $this->accountService = $accountService;
        $this->accountMapper = $accountMapper;
    }

    #[OA\Get(
        path: '/api/accounts',
        summary: 'Toon alle accounts',
        tags: ['Accounts'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van accounts',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: AccountDTO::class))
                )
            )
        ]
    )]
    #[Route('', name: 'get_all_accounts', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $accounts = $this->accountService->getAll();
        $dtos = array_map(fn($account) => $this->accountMapper->toSimpleDto($account), $accounts);

        return $this->json($dtos);
    }

    #[OA\Get(
        path: '/api/accounts/{id}',
        summary: 'Haal een specifiek account op',
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Accountgegevens',
                content: new OA\JsonContent(ref: new Model(type: AccountDTO::class))
            ),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'get_account', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        $account = $this->accountService->getById($id);
        $dto = $this->accountMapper->toSimpleDto($account);

        return $this->json($dto);
    }

    #[OA\Put(
        path: '/api/accounts/{id}',
        summary: 'Wijzig een accountnaam',
        requestBody: new OA\RequestBody(
            description: 'Nieuwe naam van het account',
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Dhr. Piet Jansen')
                ]
            )
        ),
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account succesvol bijgewerkt',
                content: new OA\JsonContent(ref: new Model(type: AccountDTO::class))
            ),
            new OA\Response(response: 400, description: 'Ongeldige input'),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/{id}', name: 'update_account', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $account = $this->accountService->update($id, $data);
        $dto = $this->accountMapper->toSimpleDto($account);

        return $this->json($dto);
    }

    #[OA\Put(
        path: '/api/accounts/{id}/default',
        summary: 'Markeer een account als default',
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID van het account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Account succesvol als default ingesteld',
                content: new OA\JsonContent(ref: new Model(type: AccountDTO::class))
            ),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/{id}/default', name: 'set_default_account', methods: ['PUT'])]
    public function setDefault(int $id): JsonResponse
    {
        $account = $this->accountService->setDefault($id);
        $dto = $this->accountMapper->toSimpleDto($account);

        return $this->json($dto);
    }
}