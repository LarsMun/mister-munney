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
     * Geeft een preview van wat er gebeurt bij het verwijderen van een categorie.
     *
     * @param int $id ID van de categorie
     * @param int $accountId ID van het account waartoe de categorie moet behoren
     * @return array Associatieve array met informatie over de delete preview
     *
     * @throws NotFoundHttpException Als de categorie niet bestaat of niet bij het account hoort
     */
    public function previewDelete(int $id, int $accountId): array
    {
        $category = $this->getById($id, $accountId);

        // Tel gekoppelde transacties
        $transactionCount = $this->transactionRepository->count(['category' => $category]);

        // Tel gekoppelde patronen
        $patternCount = $this->patternRepository->count(['category' => $category]);

        return [
            'canDelete' => $transactionCount === 0,
            'transactionCount' => $transactionCount,
            'patternCount' => $patternCount,
            'categoryName' => $category->getName(),
            'message' => $transactionCount > 0
                ? sprintf('This category has %d linked transaction(s) and cannot be deleted. Please merge it into another category first.', $transactionCount)
                : 'This category can be safely deleted.'
        ];
    }

    /**
     * Verwijdert een categorie op basis van ID binnen het opgegeven account.
     * Verwijdert ook patronen die deze categorie gebruiken.
     *
     * @param int $id ID van de categorie
     * @param int $accountId ID van het account waartoe de categorie moet behoren
     *
     * @throws NotFoundHttpException Als de categorie niet bestaat of niet bij het account hoort
     * @throws ConflictHttpException Als de categorie nog gekoppelde transacties heeft
     */
    public function delete(int $id, int $accountId): void
    {
        $category = $this->getById($id, $accountId);

        // Controleer of er transacties gekoppeld zijn
        $transactionCount = $this->transactionRepository->count(['category' => $category]);

        if ($transactionCount > 0) {
            throw new ConflictHttpException(
                sprintf(
                    'Cannot delete category with %d linked transaction(s). Please merge this category into another category first.',
                    $transactionCount
                )
            );
        }

        // Vind en verwijder alle patronen die deze categorie gebruiken
        $patterns = $this->patternRepository->findBy(['category' => $category]);
        foreach ($patterns as $pattern) {
            $this->patternRepository->remove($pattern);
        }

        $this->categoryRepository->remove($category);
    }

    /**
     * Geeft een preview van wat er gebeurt bij het mergen van twee categorieën.
     *
     * @param int $sourceId ID van de bron categorie (wordt verwijderd)
     * @param int $targetId ID van de doel categorie (ontvangt transacties)
     * @param int $accountId ID van het account
     * @return array Preview informatie
     *
     * @throws NotFoundHttpException Als een van de categorieën niet bestaat
     * @throws BadRequestHttpException Als de categorieën niet samengevoegd kunnen worden
     */
    public function previewMerge(int $sourceId, int $targetId, int $accountId): array
    {
        // Valideer categorieën
        $source = $this->getById($sourceId, $accountId);
        $target = $this->getById($targetId, $accountId);

        // Check dat het niet dezelfde categorie is
        if ($sourceId === $targetId) {
            throw new BadRequestHttpException('Cannot merge a category into itself');
        }

        // Check dat ze bij hetzelfde account horen
        if ($source->getAccount()->getId() !== $target->getAccount()->getId()) {
            throw new BadRequestHttpException('Categories must belong to the same account');
        }

        // Tel transacties
        $transactionCount = $this->transactionRepository->count(['category' => $source]);

        // Bereken totaalbedrag van te verplaatsen transacties
        $transactions = $this->transactionRepository->findBy(['category' => $source]);
        $totalAmountInCents = 0;
        $firstDate = null;
        $lastDate = null;

        foreach ($transactions as $transaction) {
            $totalAmountInCents += $transaction->getAmount()->getAmount();
            $transactionDate = $transaction->getDate();

            if ($firstDate === null || $transactionDate < $firstDate) {
                $firstDate = $transactionDate;
            }
            if ($lastDate === null || $transactionDate > $lastDate) {
                $lastDate = $transactionDate;
            }
        }

        // Tel huidige transacties van target
        $targetTransactionCount = $this->transactionRepository->count(['category' => $target]);

        return [
            'sourceCategory' => [
                'id' => $source->getId(),
                'name' => $source->getName(),
                'color' => $source->getColor(),
                'icon' => $source->getIcon(),
            ],
            'targetCategory' => [
                'id' => $target->getId(),
                'name' => $target->getName(),
                'color' => $target->getColor(),
                'icon' => $target->getIcon(),
            ],
            'transactionsToMove' => $transactionCount,
            'totalAmount' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents($totalAmountInCents)
            ),
            'dateRange' => [
                'first' => $firstDate?->format('Y-m-d'),
                'last' => $lastDate?->format('Y-m-d'),
            ],
            'targetCurrentTransactionCount' => $targetTransactionCount,
            'targetNewTransactionCount' => $targetTransactionCount + $transactionCount,
        ];
    }

    /**
     * Merge twee categorieën: verplaats alle transacties van source naar target en verwijder source.
     *
     * @param int $sourceId ID van de bron categorie (wordt verwijderd)
     * @param int $targetId ID van de doel categorie (ontvangt transacties)
     * @param int $accountId ID van het account
     * @return array Resultaat van de merge operatie
     *
     * @throws NotFoundHttpException Als een van de categorieën niet bestaat
     * @throws BadRequestHttpException Als de categorieën niet samengevoegd kunnen worden
     */
    public function mergeCategories(int $sourceId, int $targetId, int $accountId): array
    {
        // Valideer categorieën (hergebruik logic van preview)
        $source = $this->getById($sourceId, $accountId);
        $target = $this->getById($targetId, $accountId);

        // Check dat het niet dezelfde categorie is
        if ($sourceId === $targetId) {
            throw new BadRequestHttpException('Cannot merge a category into itself');
        }

        // Check dat ze bij hetzelfde account horen
        if ($source->getAccount()->getId() !== $target->getAccount()->getId()) {
            throw new BadRequestHttpException('Categories must belong to the same account');
        }

        // Haal alle transacties van source op
        $transactions = $this->transactionRepository->findBy(['category' => $source]);
        $transactionCount = count($transactions);

        // Store source name before deletion
        $sourceName = $source->getName();
        $targetName = $target->getName();

        // Verplaats alle transacties naar target (Doctrine gebruikt automatisch transactions bij flush)
        foreach ($transactions as $transaction) {
            $transaction->setCategory($target);
        }

        // Update alle patronen die naar source wijzen
        $patterns = $this->patternRepository->findBy(['category' => $source]);
        foreach ($patterns as $pattern) {
            $pattern->setCategory($target);
        }

        // Verwijder source categorie (dit triggert ook cascade deletes if configured)
        $this->categoryRepository->remove($source);

        return [
            'success' => true,
            'transactionsMoved' => $transactionCount,
            'sourceDeleted' => true,
            'message' => sprintf(
                'Successfully merged %d transaction(s) from "%s" to "%s"',
                $transactionCount,
                $sourceName,
                $targetName
            ),
        ];
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

            // Haal huidige maand bedrag op
            $currentMonthTotal = $this->transactionRepository->getCurrentMonthTotalByCategory(
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
                'currentMonthAmount' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents($currentMonthTotal)
                ),
            ];
        }, $categoryStats);

        return [
            'categories' => $formattedCategories,
            'totalSpent' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents($totalSpentInCents)
            ),
        ];
    }

    /**
     * Haalt historische data op voor een specifieke categorie, gegroepeerd per maand.
     *
     * @param int $accountId
     * @param int $categoryId
     * @param int|null $monthLimit Aantal maanden terug te gaan (null = alle maanden)
     * @return array Historische data met categorie-informatie en maandelijkse totalen
     * @throws NotFoundHttpException
     */
    public function getCategoryHistory(int $accountId, int $categoryId, ?int $monthLimit = null): array
    {
        // Verificatie account
        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account niet gevonden");
        }

        // Verificatie categorie
        $category = $this->categoryRepository->find($categoryId);
        if (!$category) {
            throw new NotFoundHttpException("Categorie niet gevonden");
        }

        // Haal maandelijkse totalen op
        $monthlyData = $this->transactionRepository->getMonthlyTotalsByCategory($accountId, $categoryId, $monthLimit);

        // Tel transacties per maand
        $transactionCounts = [];
        foreach ($monthlyData as $row) {
            $month = $row['month'];
            // We kunnen geen transacties per maand tellen vanuit de getMonthlyTotalsByCategory query
            // Voor nu laten we dit 0, of we kunnen een aparte query maken
            $transactionCounts[$month] = 0;
        }

        // Format de history array
        $history = array_map(function($row) {
            return [
                'month' => $row['month'],
                'total' => $this->moneyFactory->toFloat(
                    $this->moneyFactory->fromCents((int)$row['total'])
                ),
                'transactionCount' => 0 // TODO: Add transaction count per month if needed
            ];
        }, $monthlyData);

        // Bereken totalen
        $totalAmountInCents = array_sum(array_column($monthlyData, 'total'));
        $monthCount = count($monthlyData);
        $averagePerMonthInCents = $monthCount > 0 ? (int)($totalAmountInCents / $monthCount) : 0;

        return [
            'category' => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'color' => $category->getColor(),
                'icon' => $category->getIcon(),
            ],
            'history' => $history,
            'totalAmount' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents($totalAmountInCents)
            ),
            'averagePerMonth' => $this->moneyFactory->toFloat(
                $this->moneyFactory->fromCents($averagePerMonthInCents)
            ),
            'monthCount' => $monthCount,
        ];
    }
}