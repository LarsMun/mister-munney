<?php

namespace App\Pattern\Controller;

use App\Account\Repository\AccountRepository;
use App\Mapper\PayloadMapper;
use App\Pattern\DTO\CreatePatternDTO;
use App\Pattern\Service\MatchingPatternService;
use App\Shared\Controller\AccountOwnershipTrait;
use App\Transaction\DTO\TransactionMatchesDTO;
use Nelmio\ApiDocBundle\Attribute\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Patterns',
    description: 'Beheer van patterns'
)]
#[Route('/api/account/{accountId}/patterns')]
class MatchingPatternController extends AbstractController
{
    use AccountOwnershipTrait;

    private MatchingPatternService $matchingPatternService;
    private PayloadMapper $payloadMapper;
    private ValidatorInterface $validator;
    private AccountRepository $accountRepository;

    public function __construct
    (
        MatchingPatternService $matchingPatternService,
        PayloadMapper $payloadMapper,
        ValidatorInterface $validator,
        AccountRepository $accountRepository
    )
    {
        $this->matchingPatternService = $matchingPatternService;
        $this->payloadMapper = $payloadMapper;
        $this->validator = $validator;
        $this->accountRepository = $accountRepository;
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    #[OA\Post(
        path: '/api/account/{accountId}/patterns/match',
        summary: 'Zoek transacties die overeenkomen met een patroon',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: CreatePatternDTO::class))
        ),
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', default: 1, maximum: 2147483647, minimum: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lijst van overeenkomende transacties',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'total', type: 'integer', example: 134),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: TransactionMatchesDTO::class))
                        )
                    ],
                    type: 'object'
                )
            )
        ]
    )]
    #[Route('/match', name: 'pattern_match_preview', methods: ['POST'])]
    public function matchTransactions(
        int $accountId,
        Request $request
    ): JsonResponse {
        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($accountId)) {
            return $error;
        }

        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return $this->json(['error' => 'Ongeldige JSON-body'], 400);
            }

            /** @var CreatePatternDTO $dto */
            $dto = $this->payloadMapper->map($data, new CreatePatternDTO(), true);

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                return $this->json(['error' => (string) $errors], 400);
            }

            $result = $this->matchingPatternService->findMatchingTransactions($dto);

            return $this->json([
                'total' => $result['total'],
                'data' => $result['data'],
                'loaded' => true
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

}