<?php

namespace App\Pattern\Controller;

use App\Account\Repository\AccountRepository;
use App\Mapper\PayloadMapper;
use App\Pattern\DTO\AssignPatternDateRangeDTO;
use App\Pattern\Service\PatternAssignService;
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
    name: 'Patterns Assign',
    description: 'Toewijzen van categories of savingsaccounts op basis van patterns'
)]
#[Route('/api/account/{accountId}/patterns')]
class PatternAssignController extends AbstractController
{
    use AccountOwnershipTrait;

    private PatternAssignService $patternAssignService;
    private PayloadMapper $payloadMapper;
    private ValidatorInterface $validator;
    private AccountRepository $accountRepository;

    public function __construct
    (
        PatternAssignService $patternAssignService,
        PayloadMapper $payloadMapper,
        ValidatorInterface $validator,
        AccountRepository $accountRepository
    )
    {
        $this->patternAssignService = $patternAssignService;
        $this->payloadMapper = $payloadMapper;
        $this->validator = $validator;
        $this->accountRepository = $accountRepository;
    }

    protected function getAccountRepository(): AccountRepository
    {
        return $this->accountRepository;
    }

    #[Route('/assign', name: 'assign_transactions_to_patterns', methods: ['PATCH'])]
    #[OA\Patch(
        summary: 'Wijs bestaande transacties toe op basis van bestaande patterns',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: AssignPatternDateRangeDTO::class))
        ),
        parameters: [
            new OA\Parameter(
                name: 'accountId',
                description: 'ID van het account waarvoor transacties geëvalueerd worden',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', maximum: 2147483647, minimum: 1, example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Aantal gekoppelde transacties'),
            new OA\Response(response: 400, description: 'Validatiefout')
        ]
    )]
    public function assignToTransactions(int $accountId, Request $request): JsonResponse
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
            $dto = $this->payloadMapper->map($data, new AssignPatternDateRangeDTO(), true);
        } catch (InvalidPropertyPathException $e) {
            throw new BadRequestHttpException('Ongeldige invoervelden gedetecteerd: ' . $e->getMessage());
        }

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 400);
        }

        $count = $this->patternAssignService->assign($accountId, $dto);

        return $this->json(['matchedTransactions' => $count]);
    }
}