<?php

namespace App\Category\Service;

use App\Account\Repository\AccountRepository;
use App\Category\DTO\CategoryWithTransactionsDTO;
use App\Category\Mapper\CategoryMapper;
use App\Category\Repository\CategoryRepository;
use App\Entity\Category;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Pattern\Repository\PatternRepository;
use App\Transaction\Repository\TransactionRepository;
use App\Transaction\Service\TransactionService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ValueError;

class CategoryService
{
    private CategoryRepository $categoryRepository;
    private CategoryMapper $categoryMapper;
    private AccountRepository $accountRepository;
    private TransactionService $transactionService;
    private TransactionRepository $transactionRepository;
    private PatternRepository $patternRepository;
    private MoneyFactory $moneyFactory;

    public function __construct(
        CategoryRepository $categoryRepository,
        CategoryMapper $categoryMapper,
        AccountRepository $accountRepository,
        TransactionService $transactionService,
        TransactionRepository $transactionRepository,
        PatternRepository $patternRepository,
        MoneyFactory $moneyFactory,
    )
    {
        $this->categoryRepository = $categoryRepository;
        $this->categoryMapper = $categoryMapper;
        $this->accountRepository = $accountRepository;
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->patternRepository = $patternRepository;
        $this->moneyFactory = $moneyFactory;
    }

    /**
     * Haalt alle categorieën op die gekoppeld zijn aan een specifiek account.
     *
     * @param int $accountId ID van het account waarvoor de categorieën opgehaald moeten worden
     * @return Category[] Lijst van categorieën die bij het opgegeven account horen
     *
     * @throws NotFoundHttpException Als het account niet gevonden is
     */
    public function getAllByAccount(int $accountId): array
    {
        $account = $this->accountRepository->find($accountId);

        if (!$account) {
            throw new NotFoundHttpException('Account niet gevonden.');
        }

        return $this->categoryRepository->findBy(['account' => $account]);
    }

    /**
     * Haalt een specifieke categorie op aan de hand van het ID, optioneel binnen een specifiek account.
     *
     * @param int $id ID van de categorie
     * @param int|null $accountId Optioneel ID van het account waar de categorie toe moet behoren
     * @return Category De gevonden categorie
     *
     * @throws NotFoundHttpException Als de categorie niet bestaat of niet bij het opgegeven account hoort
     */
    public function getById(int $id, ?int $accountId = null): Category
    {
        $category = $this->categoryRepository->getById($id);

        if (!$category) {
            throw new NotFoundHttpException('Categorie niet gevonden.');
        }

        if ($accountId !== null && $category->getAccount()->getId() !== $accountId) {
            throw new NotFoundHttpException('Categorie behoort niet tot dit account.');
        }

        return $category;
    }

    /**
     * Haalt een specifieke categorie op aan de hand van het ID, optioneel binnen een specifiek account, samen met alle bijbehorende transacties.
     *
     * @param int $id ID van de categorie
     * @param int|null $accountId Optioneel ID van het account waar de categorie toe moet behoren
     * @return CategoryWithTransactionsDTO De gevonden categorie met bijbehorende transacties
     *
     * @throws NotFoundHttpException Als de categorie niet bestaat of niet bij het opgegeven account hoort
     */
    public function getWithTransactions(int $id, int $accountId = null): CategoryWithTransactionsDTO
    {
        $category = $this->getById($id, $accountId);

        $transactions = $this->transactionService->getByCategory($category->getId());
        return $this->categoryMapper->toDtoWithTransactions($category, $transactions);
    }

    /**
     * Maakt een nieuwe categorie aan binnen het opgegeven account.
     *
     * Valideert of naam aanwezig is en controleert op duplicaten binnen het account.
     *
     * @param int $accountId ID van het account waaraan de categorie wordt toegevoegd
     * @param array $data Associatieve array met ten minste 'name'
     * @return Category De aangemaakte categorie
     *
     * @throws BadRequestHttpException Als verplichte velden ontbreken
     * @throws NotFoundHttpException Als het account niet bestaat
     * @throws ConflictHttpException Als de categorie al bestaat binnen het account
     */
    public function create(int $accountId, array $data): Category
    {
        if (empty($data['name'])) {
            throw new BadRequestHttpException('Naam is verplicht.');
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException('Account niet gevonden.');
        }

        $existing = $this->categoryRepository->findOneBy([
            'name' => $data['name'],
            'account' => $account
        ]);

        if ($existing) {
            throw new ConflictHttpException("Een categorie met deze naam bestaat al voor dit account.");
        }

        $category = new Category();
        $category->setName($data['name']);
        $category->setIcon($data['icon'] ?? null);
        $category->setColor($data['color'] ?? null);
        $category->setAccount($account);

        $this->categoryRepository->save($category);

        return $category;
    }

    /**
     * Werkt een bestaande categorie bij binnen het opgegeven account.
     *
     * @param int $id ID van de te updaten categorie
     * @param int $accountId ID van het account dat eigenaar moet zijn van de categorie
     * @param array $data Gegevens om bij te werken (name, icon, color)
     * @return Category De bijgewerkte categorie
     *
     * @throws ConflictHttpException Als de nieuwe naam al bestaat binnen het account
     * @throws NotFoundHttpException Als de categorie niet bestaat of niet bij dit account hoort
     */
    public function update(int $id, int $accountId, array $data): Category
    {
        $category = $this->getById($id, $accountId);

        if (!empty($data['name']) && $data['name'] !== $category->getName()) {
            $duplicate = $this->categoryRepository->findOneBy([
                'name' => $data['name'],
                'account' => $category->getAccount()
            ]);

            if ($duplicate) {
                throw new ConflictHttpException("Een categorie met deze naam bestaat al voor dit account.");
            }

            $category->setName($data['name']);
        }

        if (array_key_exists('icon', $data)) {
            $category->setIcon($data['icon']);
        }

        if (array_key_exists('color', $data)) {
            $category->setColor($data['color']);
        }

        $this->categoryRepository->save($category);

        return $category;
    }

    /**
     * Verwijdert een categorie op basis van ID binnen het opgegeven account.
     * Verwijdert ook patronen die deze categorie gebruiken en geen savingsAccount hebben.
     *
     * @param int $id ID van de categorie
     * @param int $accountId ID van het account waartoe de categorie moet behoren
     *
     * @throws NotFoundHttpException Als de categorie niet bestaat of niet bij het account hoort
     */
    public function delete(int $id, int $accountId): void
    {
        $category = $this->getById($id, $accountId);
        // Vind alle patronen die deze categorie gebruiken
        $patterns = $this->patternRepository->findBy(['category' => $category]);

        // Verwijder patronen die geen savingsAccount hebben
        foreach ($patterns as $pattern) {
            if ($pattern->getSavingsAccount() === null) {
                $this->patternRepository->remove($pattern);
            }
        }

        $this->categoryRepository->remove($category);
    }

    /**
     * Haalt statistieken per categorie op.
     *
     * @param int $accountId
     * @param string|int $months 'all' of een getal voor aantal maanden
     * @return array
     * @throws NotFoundHttpException
     */
    public function getCategoryStatistics(int $accountId, string|int $months): array
    {
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account niet gevonden");
        }

        // Bepaal het aantal maanden
        $monthLimit = null;
        if ($months !== 'all') {
            $monthLimit = is_numeric($months) ? (int)$months : null;
            if ($monthLimit !== null && $monthLimit < 1) {
                throw new BadRequestHttpException("Months parameter moet groter zijn dan 0 of 'all'");
            }
        }

        // Haal statistieken op
        $categoryStats = $this->transactionRepository->getCategoryStatistics($accountId, $monthLimit);

        if (empty($categoryStats)) {
            return [
                'categories' => [],
                'totalSpent' => 0.00,
            ];
        }

        // Bereken totaal bedrag voor percentage berekening
        $totalSpentInCents = array_sum(array_column($categoryStats, 'totalAmount'));

        // Format de data met correcte averagePerMonth EN recente statistieken
        $formattedCategories = array_map(function($stat) use ($accountId, $monthLimit, $totalSpentInCents) {
            $categoryId = (int)$stat['categoryId'];
            $totalAmount = (int)$stat['totalAmount'];
            $transactionCount = (int)$stat['transactionCount'];
            $averagePerTransaction = (int)$stat['averagePerTransaction'];

            // Haal maandelijkse totalen op voor deze categorie
            $monthlyTotals = $this->transactionRepository->getMonthlyTotalsByCategory(
                $accountId,
                $categoryId,
                $monthLimit
            );

            // Bereken gemiddelde per maand op basis van werkelijke maandelijkse uitgaven
            $averagePerMonth = 0;
            if (!empty($monthlyTotals)) {
                $monthlyAmounts = array_map(fn($row) => (int)$row['total'], $monthlyTotals);
                $averagePerMonth = array_sum($monthlyAmounts) / count($monthlyAmounts);
            }

            // Haal recente statistieken op (mediaan + trend)
            $recentStats = $this->transactionRepository->getCategoryRecentStatistics(
                $accountId,
                $categoryId
            );

            // Bereken percentage
            $percentage = $totalSpentInCents > 0
                ? round(($totalAmount / $totalSpentInCents) * 100, 2)
                : 0;

            return [
                'categoryId' => $categoryId,
                'categoryName' => $stat['categoryName'],
                'categoryColor' => $stat['categoryColor'],
                'categoryIcon' => $stat['categoryIcon'],
                'transactionType' => $stat['transactionType'] ?? 'debit',
                'totalAmount' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents($totalAmount)
                ),
                'transactionCount' => $transactionCount,
                'averagePerTransaction' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents($averagePerTransaction)
                ),
                'averagePerMonth' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents((int)$averagePerMonth)
                ),
                'monthsWithExpenses' => count($monthlyTotals),
                'percentageOfTotal' => $percentage,
                // Nieuwe velden voor recente statistieken
                'medianLast12Months' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents($recentStats['medianLast12Months'])
                ),
                'medianAll' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents($recentStats['medianAll'])
                ),
                'trend' => $recentStats['trend'],
                'trendPercentage' => $recentStats['trendPercentage'],
            ];
        }, $categoryStats);

        return [
            'categories' => $formattedCategories,
            'totalSpent' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents($totalSpentInCents)
            ),
        ];
    }
}