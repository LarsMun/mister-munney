<?php

namespace App\Account\Controller;

use App\Account\DTO\AccountDTO;
use App\Account\Mapper\AccountMapper;
use App\Account\Service\AccountService;
use App\Money\MoneyFactory;
use App\Transaction\Repository\TransactionRepository;
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
    private TransactionRepository $transactionRepository;
    private MoneyFactory $moneyFactory;

    public function __construct(
        AccountService $accountService,
        AccountMapper $accountMapper,
        TransactionRepository $transactionRepository,
        MoneyFactory $moneyFactory
    ) {
        $this->accountService = $accountService;
        $this->accountMapper = $accountMapper;
        $this->transactionRepository = $transactionRepository;
        $this->moneyFactory = $moneyFactory;
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
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Filter accounts to only those owned by the authenticated user
        $accounts = $this->accountService->getAll();
        $userAccounts = array_filter($accounts, fn($account) => $account->isOwnedBy($user));
        $dtos = array_map(fn($account) => $this->accountMapper->toSimpleDto($account), $userAccounts);

        return $this->json(array_values($dtos)); // array_values to reindex array
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
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $account = $this->accountService->getById($id);

        // Verify ownership
        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $dto = $this->accountMapper->toSimpleDto($account);

        return $this->json($dto);
    }

    #[OA\Put(
        path: '/api/accounts/{id}',
        summary: 'Wijzig een account',
        requestBody: new OA\RequestBody(
            description: 'Accountgegevens',
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Dhr. Piet Jansen'),
                    new OA\Property(property: 'type', type: 'string', enum: ['CHECKING', 'SAVINGS'], example: 'CHECKING')
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
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Verify ownership before update
        $account = $this->accountService->getById($id);
        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

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
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        // Verify ownership before setting default
        $account = $this->accountService->getById($id);
        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $account = $this->accountService->setDefault($id);
        $dto = $this->accountMapper->toSimpleDto($account);

        return $this->json($dto);
    }

    #[OA\Get(
        path: '/api/accounts/{id}/savings-history',
        summary: 'Haal spaarrekening historie op',
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID van het spaarrekening account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Maandelijkse historie van bij- en afschrijvingen'
            ),
            new OA\Response(response: 403, description: 'Geen toegang tot dit account'),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/{id}/savings-history', name: 'get_savings_history', methods: ['GET'])]
    public function getSavingsHistory(int $id): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $account = $this->accountService->getById($id);

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // Haal maandelijkse historie op
        $monthlyHistory = $this->transactionRepository->getMonthlySavingsHistory($id);

        // Bereken totalen
        $totalDeposits = 0;
        $totalWithdrawals = 0;
        $history = [];

        foreach ($monthlyHistory as $month) {
            $deposits = (int) $month['deposits'];
            $withdrawals = (int) $month['withdrawals'];
            $netChange = (int) $month['net_change'];

            $totalDeposits += $deposits;
            $totalWithdrawals += $withdrawals;

            $history[] = [
                'month' => $month['month'],
                'deposits' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($deposits)),
                'withdrawals' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($withdrawals)),
                'netChange' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($netChange)),
                'transactionCount' => (int) $month['transaction_count'],
            ];
        }

        return $this->json([
            'account' => [
                'id' => $account->getId(),
                'name' => $account->getName(),
                'accountNumber' => $account->getAccountNumber(),
            ],
            'history' => $history,
            'totalDeposits' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($totalDeposits)),
            'totalWithdrawals' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($totalWithdrawals)),
            'currentBalance' => $this->moneyFactory->toFloat($this->moneyFactory->fromCents($totalDeposits - $totalWithdrawals)),
            'monthCount' => count($history),
        ]);
    }

    #[OA\Get(
        path: '/api/accounts/{id}/savings-transactions/{month}',
        summary: 'Haal transacties van een spaarrekening voor een specifieke maand op',
        tags: ['Accounts'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID van het spaarrekening account',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'month',
                description: 'Maand in YYYY-MM formaat',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', example: '2024-01')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transacties voor de opgegeven maand'
            ),
            new OA\Response(response: 403, description: 'Geen toegang tot dit account'),
            new OA\Response(response: 404, description: 'Account niet gevonden')
        ]
    )]
    #[Route('/{id}/savings-transactions/{month}', name: 'get_savings_transactions', methods: ['GET'])]
    public function getSavingsTransactions(int $id, string $month): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], 401);
        }

        $account = $this->accountService->getById($id);

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        // Valideer maand formaat
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $this->json(['error' => 'Invalid month format. Use YYYY-MM'], 400);
        }

        $transactions = $this->transactionRepository->getSavingsTransactionsForMonth($id, $month);

        $result = array_map(function ($transaction) {
            return [
                'id' => $transaction->getId(),
                'date' => $transaction->getDate()->format('Y-m-d'),
                'description' => $transaction->getDescription(),
                'amount' => $this->moneyFactory->toFloat($transaction->getAmountAsMoney()),
                'type' => $transaction->getTransactionType()->value,
                'counterpartyAccount' => $transaction->getCounterpartyAccount(),
            ];
        }, $transactions);

        return $this->json([
            'month' => $month,
            'transactions' => $result,
            'count' => count($result),
        ]);
    }
}