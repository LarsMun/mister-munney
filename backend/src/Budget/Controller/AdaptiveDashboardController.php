<?php

namespace App\Budget\Controller;

use App\Budget\DTO\ActiveBudgetDTO;
use App\Budget\DTO\CreateProjectDTO;
use App\Budget\DTO\UpdateProjectDTO;
use App\Budget\DTO\ProjectDetailsDTO;
use App\Account\Repository\AccountRepository;
use App\Budget\Repository\BudgetRepository;
use App\Budget\Service\ActiveBudgetService;
use App\Budget\Service\BudgetInsightsService;
use App\Budget\Service\ProjectAggregatorService;
use App\Budget\Service\ProjectStatusCalculator;
use App\Entity\Budget;
use App\Entity\ExternalPayment;
use App\Enum\BudgetType;
use App\Enum\ProjectStatus;
use App\FeatureFlag\Service\FeatureFlagService;
use App\Money\MoneyFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[Route('/api/budgets')]
class AdaptiveDashboardController extends AbstractController
{
    public function __construct(
        private readonly ActiveBudgetService $activeBudgetService,
        private readonly BudgetInsightsService $budgetInsightsService,
        private readonly ProjectAggregatorService $projectAggregatorService,
        private readonly ProjectStatusCalculator $statusCalculator,
        private readonly BudgetRepository $budgetRepository,
        private readonly AccountRepository $accountRepository,
        private readonly FeatureFlagService $featureFlagService,
        private readonly MoneyFactory $moneyFactory,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager
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

    /**
     * Verify that the authenticated user owns the budget (through budget's account)
     */
    private function verifyBudgetOwnership(int $budgetId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $budget = $this->budgetRepository->find($budgetId);
        if (!$budget) {
            return $this->json(['error' => 'Budget not found'], Response::HTTP_NOT_FOUND);
        }

        $account = $budget->getAccount();
        if (!$account) {
            return $this->json(['error' => 'Budget has no associated account'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }

    /**
     * Get active budgets (EXPENSE/INCOME with recent activity, or ACTIVE projects)
     */
    #[Route('/active', name: 'get_active_budgets', methods: ['GET'])]
    public function getActiveBudgets(Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('living_dashboard')) {
            throw new AccessDeniedHttpException('Living dashboard feature is disabled');
        }

        $months = $request->query->getInt('months', 2);
        $type = $request->query->get('type'); // optional: EXPENSE, INCOME, PROJECT
        $startDate = $request->query->get('startDate'); // YYYY-MM-DD
        $endDate = $request->query->get('endDate'); // YYYY-MM-DD
        $accountId = $request->query->getInt('accountId'); // Filter by account

        // Verify account ownership if accountId is provided
        if ($accountId > 0) {
            if ($error = $this->verifyAccountOwnership($accountId)) {
                return $error;
            }
        }

        $budgetType = $type ? BudgetType::from($type) : null;

        // Get active budgets filtered by account
        $activeBudgets = $this->activeBudgetService->getActiveBudgets($months, $budgetType, $accountId);

        // Separate by type
        $expenseIncomeBudgets = array_filter(
            $activeBudgets,
            fn(Budget $b) => $b->getBudgetType() !== BudgetType::PROJECT
        );

        // Compute insights for EXPENSE/INCOME if feature enabled
        $insights = [];
        if ($this->featureFlagService->isEnabled('behavioral_insights')) {
            $insights = $this->budgetInsightsService->computeInsights(
                $expenseIncomeBudgets,
                limit: null,
                startDate: $startDate,
                endDate: $endDate
            );
            // Index by budgetId
            $insights = array_column($insights, null, 'budgetId');
        }

        // Map to DTOs
        $dtos = array_map(function (Budget $budget) use ($insights) {
            $dto = new ActiveBudgetDTO();
            $dto->id = $budget->getId();
            $dto->name = $budget->getName();
            $dto->budgetType = $budget->getBudgetType()->value;
            $dto->description = $budget->getDescription();

            // Calculate status for projects
            if ($budget->isProject()) {
                $dto->durationMonths = $budget->getDurationMonths();
                $calculatedStatus = $this->statusCalculator->calculateStatus($budget);
                $dto->status = $calculatedStatus->value;
            }

            $dto->categoryCount = $budget->getCategories()->count();

            // Add insight if available
            if (isset($insights[$budget->getId()])) {
                $dto->insight = $insights[$budget->getId()];
            }

            return $dto;
        }, $activeBudgets);

        return $this->json($dtos);
    }

    /**
     * Get older/inactive budgets
     */
    #[Route('/older', name: 'get_older_budgets', methods: ['GET'])]
    public function getOlderBudgets(Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('living_dashboard')) {
            throw new AccessDeniedHttpException('Living dashboard feature is disabled');
        }

        $months = $request->query->getInt('months', 2);
        $type = $request->query->get('type');
        $accountId = $request->query->getInt('accountId'); // Filter by account

        // Verify account ownership if accountId is provided
        if ($accountId > 0) {
            if ($error = $this->verifyAccountOwnership($accountId)) {
                return $error;
            }
        }

        $budgetType = $type ? BudgetType::from($type) : null;

        $olderBudgets = $this->activeBudgetService->getOlderBudgets($months, $budgetType, $accountId);

        // Simple DTOs without insights
        $dtos = array_map(function (Budget $budget) {
            return [
                'id' => $budget->getId(),
                'name' => $budget->getName(),
                'budgetType' => $budget->getBudgetType()->value,
                'categoryCount' => $budget->getCategories()->count(),
            ];
        }, $olderBudgets);

        return $this->json($dtos);
    }

    /**
     * Create a new project budget
     */
    #[Route('', name: 'create_project', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Deserialize and validate
        $createDTO = $this->serializer->deserialize(
            $request->getContent(),
            CreateProjectDTO::class,
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

        // Get and validate account
        $account = $this->accountRepository->find($createDTO->accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account with ID {$createDTO->accountId} not found");
        }

        // Verify account ownership
        if ($error = $this->verifyAccountOwnership($createDTO->accountId)) {
            return $error;
        }

        // Create project budget
        $project = new Budget();
        $project->setName($createDTO->name);
        $project->setDescription($createDTO->description);
        $project->setBudgetType(BudgetType::PROJECT);
        $project->setAccount($account);
        $project->setDurationMonths($createDTO->durationMonths);

        $this->budgetRepository->save($project);

        // Calculate status
        $calculatedStatus = $this->statusCalculator->calculateStatus($project);

        // Return created project
        return $this->json([
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'budgetType' => $project->getBudgetType()->value,
            'durationMonths' => $project->getDurationMonths(),
            'status' => $calculatedStatus->value,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a project
     */
    #[Route('/{id}', name: 'update_project', methods: ['PATCH'])]
    public function updateProject(int $id, Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Verify budget ownership
        if ($error = $this->verifyBudgetOwnership($id)) {
            return $error;
        }

        // Get project
        $project = $this->budgetRepository->find($id);

        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        if (!$project->isProject()) {
            throw new \InvalidArgumentException('Budget is not a project');
        }

        // Deserialize and validate
        $updateDTO = $this->serializer->deserialize(
            $request->getContent(),
            UpdateProjectDTO::class,
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

        // Update fields if provided
        if ($updateDTO->name !== null) {
            $project->setName($updateDTO->name);
        }

        if ($updateDTO->description !== null) {
            $project->setDescription($updateDTO->description);
        }

        if ($updateDTO->durationMonths !== null) {
            $project->setDurationMonths($updateDTO->durationMonths);
        }

        $this->budgetRepository->save($project);

        // Calculate status
        $calculatedStatus = $this->statusCalculator->calculateStatus($project);

        // Return updated project
        return $this->json([
            'id' => $project->getId(),
            'name' => $project->getName(),
            'description' => $project->getDescription(),
            'budgetType' => $project->getBudgetType()->value,
            'durationMonths' => $project->getDurationMonths(),
            'status' => $calculatedStatus->value,
        ]);
    }

    /**
     * Get project details with aggregated totals and time series
     */
    #[Route('/{id}/details', name: 'get_project_details', methods: ['GET'])]
    public function getProjectDetails(int $id): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Verify budget ownership
        if ($error = $this->verifyBudgetOwnership($id)) {
            return $error;
        }

        $project = $this->budgetRepository->find($id);

        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        if (!$project->isProject()) {
            throw new \InvalidArgumentException('Budget is not a project');
        }

        // Get aggregated data
        $totals = $this->projectAggregatorService->getProjectTotals($project);
        $timeSeries = $this->projectAggregatorService->getProjectTimeSeries($project);

        // Calculate status
        $calculatedStatus = $this->statusCalculator->calculateStatus($project);

        // Build DTO
        $dto = new ProjectDetailsDTO();
        $dto->id = $project->getId();
        $dto->name = $project->getName();
        $dto->description = $project->getDescription();
        $dto->durationMonths = $project->getDurationMonths();
        $dto->status = $calculatedStatus->value;
        $dto->totals = $totals;
        $dto->timeSeries = $timeSeries;
        $dto->categoryCount = $project->getCategories()->count();

        return $this->json($dto);
    }

    /**
     * Get all projects (convenience method)
     */
    #[Route('', name: 'list_projects', methods: ['GET'])]
    public function listProjects(Request $request): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        $statusFilter = $request->query->get('status'); // optional filter
        $accountId = $request->query->getInt('accountId'); // Filter by account

        // Verify account ownership if accountId is provided
        if ($accountId > 0) {
            if ($error = $this->verifyAccountOwnership($accountId)) {
                return $error;
            }
        }

        // Get all PROJECT type budgets
        $qb = $this->budgetRepository->createQueryBuilder('b')
            ->where('b.budgetType = :type')
            ->setParameter('type', BudgetType::PROJECT);

        // Filter by account if specified
        if ($accountId > 0) {
            $qb->andWhere('b.account = :accountId')
               ->setParameter('accountId', $accountId);
        }

        $projects = $qb->getQuery()->getResult();

        // Map to simple DTOs with calculated status
        $dtos = array_map(function (Budget $project) {
            $totals = $this->projectAggregatorService->getProjectTotals($project);
            $calculatedStatus = $this->statusCalculator->calculateStatus($project);

            return [
                'id' => $project->getId(),
                'name' => $project->getName(),
                'description' => $project->getDescription(),
                'durationMonths' => $project->getDurationMonths(),
                'status' => $calculatedStatus->value,
                'totals' => $totals,
                'categoryCount' => $project->getCategories()->count(),
            ];
        }, $projects);

        // Apply status filter if provided (after calculation)
        if ($statusFilter) {
            $dtos = array_filter($dtos, function ($dto) use ($statusFilter) {
                return $dto['status'] === $statusFilter;
            });
            $dtos = array_values($dtos); // Re-index array
        }

        return $this->json($dtos);
    }

    /**
     * Get project entries (merged transactions and external payments)
     */
    #[Route('/{id}/entries', name: 'get_project_entries', methods: ['GET'])]
    public function getProjectEntries(int $id): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Verify budget ownership
        if ($error = $this->verifyBudgetOwnership($id)) {
            return $error;
        }

        $project = $this->budgetRepository->find($id);

        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        if (!$project->isProject()) {
            throw new \InvalidArgumentException('Budget is not a project');
        }

        // Get merged entries
        $entries = $this->projectAggregatorService->getProjectEntries($project);

        return $this->json($entries);
    }

    /**
     * Get external payments for a project
     */
    #[Route('/{id}/external-payments', name: 'get_project_external_payments', methods: ['GET'])]
    public function getProjectExternalPayments(int $id): JsonResponse
    {
        // Check feature flag
        if (!$this->featureFlagService->isEnabled('projects')) {
            throw new AccessDeniedHttpException('Projects feature is disabled');
        }

        // Verify budget ownership
        if ($error = $this->verifyBudgetOwnership($id)) {
            return $error;
        }

        $project = $this->budgetRepository->find($id);

        if (!$project) {
            throw new NotFoundHttpException('Project not found');
        }

        if (!$project->isProject()) {
            throw new \InvalidArgumentException('Budget is not a project');
        }

        // Get external payments
        $payments = $this->entityManager
            ->getRepository(ExternalPayment::class)
            ->findBy(['budget' => $project], ['paidOn' => 'DESC']);

        // Map to DTOs
        $dtos = array_map(function ($payment) {
            return [
                'id' => $payment->getId(),
                'amount' => $this->moneyFactory->toString($payment->getAmount()),
                'paidOn' => $payment->getPaidOn()->format('Y-m-d'),
                'payerSource' => $payment->getPayerSource()->value,
                'note' => $payment->getNote(),
                'attachmentUrl' => $payment->getAttachmentUrl(),
            ];
        }, $payments);

        return $this->json($dtos);
    }
}
