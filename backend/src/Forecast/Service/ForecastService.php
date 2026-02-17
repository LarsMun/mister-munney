<?php

namespace App\Forecast\Service;

use App\Account\Repository\AccountRepository;
use App\Budget\Repository\BudgetRepository;
use App\Entity\Account;
use App\Entity\Budget;
use App\Entity\Category;
use App\Entity\ForecastItem;
use App\Forecast\DTO\ForecastItemDTO;
use App\Forecast\DTO\ForecastSummaryDTO;
use App\Forecast\Repository\ForecastItemRepository;
use App\Money\MoneyFactory;
use App\Transaction\Repository\TransactionRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ForecastService
{
    public function __construct(
        private ForecastItemRepository $forecastItemRepository,
        private TransactionRepository $transactionRepository,
        private AccountRepository $accountRepository,
        private BudgetRepository $budgetRepository,
        private MoneyFactory $moneyFactory,
    ) {}

    /**
     * Haal de complete forecast op voor een account en maand
     */
    public function getForecast(int $accountId, string $month): ForecastSummaryDTO
    {
        $items = $this->forecastItemRepository->findByAccount($accountId);

        $dto = new ForecastSummaryDTO();
        $dto->month = $month;

        foreach ($items as $item) {
            $itemDto = $this->mapItemToDto($item, $month);

            if ($item->isIncome()) {
                $dto->incomeItems[] = $itemDto;
                $dto->totalExpectedIncome += $itemDto->expectedAmount;
                $dto->totalActualIncome += $itemDto->actualAmount;
            } else {
                $dto->expenseItems[] = $itemDto;
                $dto->totalExpectedExpenses += $itemDto->expectedAmount;
                $dto->totalActualExpenses += $itemDto->actualAmount;
            }
        }

        $dto->expectedResult = $dto->totalExpectedIncome - $dto->totalExpectedExpenses;
        $dto->actualResult = $dto->totalActualIncome - $dto->totalActualExpenses;

        // Haal huidig saldo op (laatste echte balance_after + impact tijdelijke transacties)
        $currentBalanceInCents = $this->transactionRepository->getLatestBalanceForAccount($accountId);
        $tempImpactInCents = $this->transactionRepository->getTemporaryTransactionsBalanceImpact($accountId);
        $adjustedBalanceInCents = ($currentBalanceInCents ?? 0) + $tempImpactInCents;
        $dto->currentBalance = $this->moneyFactory->toFloat($this->moneyFactory->fromCents($adjustedBalanceInCents));

        // Bereken verwacht eindsaldo
        $remainingExpectedIncome = $dto->totalExpectedIncome - $dto->totalActualIncome;
        $remainingExpectedExpenses = $dto->totalExpectedExpenses - $dto->totalActualExpenses;
        $dto->projectedBalance = $dto->currentBalance + $remainingExpectedIncome - $remainingExpectedExpenses;

        return $dto;
    }

    /**
     * Voeg een budget toe aan de forecast
     */
    public function addBudget(int $accountId, int $budgetId, string $type, int $expectedAmountInCents): ForecastItem
    {
        if ($this->forecastItemRepository->existsByBudget($accountId, $budgetId)) {
            throw new BadRequestHttpException('Dit budget zit al in de forecast');
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException('Account niet gevonden');
        }

        $budget = $this->budgetRepository->findByIdAndAccount($budgetId, $account);
        if (!$budget) {
            throw new NotFoundHttpException('Budget niet gevonden');
        }

        $position = $this->forecastItemRepository->getMaxPosition($accountId, $type) + 1;

        $item = new ForecastItem();
        $item->setAccount($account);
        $item->setBudget($budget);
        $item->setType($type);
        $item->setExpectedAmountInCents($expectedAmountInCents);
        $item->setPosition($position);

        $this->forecastItemRepository->save($item);

        return $item;
    }

    /**
     * Voeg een categorie toe aan de forecast
     */
    public function addCategory(int $accountId, int $categoryId, string $type, int $expectedAmountInCents): ForecastItem
    {
        if ($this->forecastItemRepository->existsByCategory($accountId, $categoryId)) {
            throw new BadRequestHttpException('Deze categorie zit al in de forecast');
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException('Account niet gevonden');
        }

        // Haal categorie op via entity manager
        $category = $this->forecastItemRepository->getEntityManager()
            ->getRepository(Category::class)
            ->find($categoryId);

        if (!$category || $category->getAccount()->getId() !== $accountId) {
            throw new NotFoundHttpException('Categorie niet gevonden');
        }

        $position = $this->forecastItemRepository->getMaxPosition($accountId, $type) + 1;

        $item = new ForecastItem();
        $item->setAccount($account);
        $item->setCategory($category);
        $item->setType($type);
        $item->setExpectedAmountInCents($expectedAmountInCents);
        $item->setPosition($position);

        $this->forecastItemRepository->save($item);

        return $item;
    }

    /**
     * Update een forecast item
     */
    public function updateItem(int $itemId, array $data): ForecastItem
    {
        $item = $this->forecastItemRepository->find($itemId);
        if (!$item) {
            throw new NotFoundHttpException('Forecast item niet gevonden');
        }

        if (isset($data['expectedAmount'])) {
            $amountInCents = (int) round($data['expectedAmount'] * 100);
            $item->setExpectedAmountInCents($amountInCents);
        }

        if (isset($data['customName'])) {
            $item->setCustomName($data['customName'] ?: null);
        }

        if (isset($data['position'])) {
            $item->setPosition((int) $data['position']);
        }

        if (isset($data['type'])) {
            $item->setType($data['type']);
        }

        $this->forecastItemRepository->save($item);

        return $item;
    }

    /**
     * Verwijder een forecast item
     */
    public function removeItem(int $itemId): void
    {
        $item = $this->forecastItemRepository->find($itemId);
        if (!$item) {
            throw new NotFoundHttpException('Forecast item niet gevonden');
        }

        $this->forecastItemRepository->remove($item);
    }

    /**
     * Update posities van meerdere items (voor drag & drop)
     */
    public function updatePositions(array $positions): void
    {
        foreach ($positions as $pos) {
            $item = $this->forecastItemRepository->find($pos['id']);
            if ($item) {
                $item->setPosition($pos['position']);
                if (isset($pos['type'])) {
                    $item->setType($pos['type']);
                }
                $this->forecastItemRepository->save($item, false);
            }
        }
        $this->forecastItemRepository->flush();
    }

    /**
     * Haal beschikbare budgetten op die nog niet in de forecast zitten
     */
    public function getAvailableBudgets(int $accountId): array
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            return [];
        }

        $existingBudgetIds = array_map(
            fn(ForecastItem $item) => $item->getBudget()?->getId(),
            $this->forecastItemRepository->findByAccount($accountId)
        );
        $existingBudgetIds = array_filter($existingBudgetIds);

        $budgets = $this->budgetRepository->findByAccount($account);

        $available = [];
        foreach ($budgets as $budget) {
            // Filter projectbudgetten uit - die horen niet in de cashflow forecast
            if ($budget->isProject()) {
                continue;
            }

            if (!in_array($budget->getId(), $existingBudgetIds)) {
                $available[] = [
                    'id' => $budget->getId(),
                    'name' => $budget->getName(),
                    'icon' => $budget->getIcon(),
                    'type' => $budget->getBudgetType()->value,
                    'historicalMedian' => $this->calculateBudgetMedian($budget),
                ];
            }
        }

        return $available;
    }

    /**
     * Haal beschikbare categorieën op die nog niet in de forecast zitten
     */
    public function getAvailableCategories(int $accountId): array
    {
        $existingCategoryIds = array_map(
            fn(ForecastItem $item) => $item->getCategory()?->getId(),
            $this->forecastItemRepository->findByAccount($accountId)
        );
        $existingCategoryIds = array_filter($existingCategoryIds);

        $categories = $this->forecastItemRepository->getEntityManager()
            ->getRepository(Category::class)
            ->findBy(['account' => $accountId]);

        $available = [];
        foreach ($categories as $category) {
            if (!in_array($category->getId(), $existingCategoryIds)) {
                $available[] = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'icon' => $category->getIcon(),
                    'budgetId' => $category->getBudget()?->getId(),
                    'budgetName' => $category->getBudget()?->getName(),
                    'historicalMedian' => $this->calculateCategoryMedian($category, $accountId),
                ];
            }
        }

        return $available;
    }

    /**
     * Map een ForecastItem naar DTO met actuele bedragen
     */
    private function mapItemToDto(ForecastItem $item, string $month): ForecastItemDTO
    {
        $dto = new ForecastItemDTO();
        $dto->id = $item->getId();
        $dto->type = $item->getType();
        $dto->name = $item->getName();
        $dto->icon = $item->getIcon();
        $dto->budgetId = $item->getBudget()?->getId();
        $dto->categoryId = $item->getCategory()?->getId();
        $dto->expectedAmount = $this->moneyFactory->toFloat(
            $this->moneyFactory->fromCents($item->getExpectedAmountInCents())
        );
        $dto->position = $item->getPosition();
        $dto->customName = $item->getCustomName();

        // Bereken actueel bedrag
        $dto->actualAmount = $this->calculateActualAmount($item, $month);

        return $dto;
    }

    /**
     * Bereken het actuele bedrag voor een forecast item in een maand
     */
    private function calculateActualAmount(ForecastItem $item, string $month): float
    {
        $accountId = $item->getAccount()->getId();

        if ($item->getBudget()) {
            // Som van alle categorieën in dit budget
            $categoryIds = $item->getBudget()->getCategories()->map(fn($c) => $c->getId())->toArray();
            if (empty($categoryIds)) {
                return 0;
            }
            $amountInCents = $this->transactionRepository->getTotalSpentByCategoriesInMonth($categoryIds, $month);
        } elseif ($item->getCategory()) {
            // Alleen deze categorie
            $amountInCents = $this->transactionRepository->getTotalSpentByCategoriesInMonth(
                [$item->getCategory()->getId()],
                $month
            );
        } else {
            return 0;
        }

        // Voor inkomsten: gebruik abs() om CREDIT (negatief) positief te maken
        // Voor uitgaven: behoud het teken zodat spaaropnamen negatief blijven
        if ($item->isIncome()) {
            return $this->moneyFactory->toFloat($this->moneyFactory->fromCents(abs($amountInCents)));
        } else {
            return $this->moneyFactory->toFloat($this->moneyFactory->fromCents($amountInCents));
        }
    }

    /**
     * Bereken historische mediaan voor een budget
     */
    private function calculateBudgetMedian(Budget $budget): float
    {
        $categoryIds = $budget->getCategories()->map(fn($c) => $c->getId())->toArray();
        if (empty($categoryIds)) {
            return 0;
        }

        $accountId = $budget->getAccount()->getId();
        $monthlyData = $this->transactionRepository->getMonthlySpentByCategories($accountId, $categoryIds, 12);

        $amounts = array_map(fn($m) => abs($m['total']), $monthlyData);
        if (empty($amounts)) {
            return 0;
        }

        sort($amounts);
        $count = count($amounts);
        $middle = (int) floor($count / 2);

        $medianInCents = $count % 2 === 0
            ? ($amounts[$middle - 1] + $amounts[$middle]) / 2
            : $amounts[$middle];

        return $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int) $medianInCents));
    }

    /**
     * Bereken historische mediaan voor een categorie
     */
    private function calculateCategoryMedian(Category $category, int $accountId): float
    {
        $monthlyData = $this->transactionRepository->getMonthlySpentByCategories($accountId, [$category->getId()], 12);

        $amounts = array_map(fn($m) => abs($m['total']), $monthlyData);
        if (empty($amounts)) {
            return 0;
        }

        sort($amounts);
        $count = count($amounts);
        $middle = (int) floor($count / 2);

        $medianInCents = $count % 2 === 0
            ? ($amounts[$middle - 1] + $amounts[$middle]) / 2
            : $amounts[$middle];

        return $this->moneyFactory->toFloat($this->moneyFactory->fromCents((int) $medianInCents));
    }

    /**
     * Haal de mediaan op voor een forecast item
     */
    public function getItemMedian(int $itemId): float
    {
        $item = $this->forecastItemRepository->find($itemId);
        if (!$item) {
            throw new NotFoundHttpException('Forecast item niet gevonden');
        }

        $accountId = $item->getAccount()->getId();

        if ($item->getBudget()) {
            return $this->calculateBudgetMedian($item->getBudget());
        } elseif ($item->getCategory()) {
            return $this->calculateCategoryMedian($item->getCategory(), $accountId);
        }

        return 0;
    }

    /**
     * Reset een forecast item naar de historische mediaan
     */
    public function resetItemToMedian(int $itemId): ForecastItem
    {
        $item = $this->forecastItemRepository->find($itemId);
        if (!$item) {
            throw new NotFoundHttpException('Forecast item niet gevonden');
        }

        $median = $this->getItemMedian($itemId);
        $medianInCents = (int) round($median * 100);
        $item->setExpectedAmountInCents($medianInCents);

        $this->forecastItemRepository->save($item);

        return $item;
    }

    /**
     * Reset alle forecast items van een type naar hun mediaan
     */
    public function resetTypeToMedian(int $accountId, string $type): int
    {
        $items = $this->forecastItemRepository->findByAccountAndType($accountId, $type);
        $count = 0;

        foreach ($items as $item) {
            $median = $this->getItemMedian($item->getId());
            $medianInCents = (int) round($median * 100);
            $item->setExpectedAmountInCents($medianInCents);
            $this->forecastItemRepository->save($item, false);
            $count++;
        }

        $this->forecastItemRepository->flush();

        return $count;
    }
}
