// frontend/src/domains/categories/CategoriesPage.tsx

import { useState, useMemo } from 'react';
import { useAccount } from '../../app/context/AccountContext';
import { useCategories } from './hooks/useCategories';
import { useCategoryStatistics } from './hooks/useCategoryStatistics';
import CategoryList from './components/CategoryList';
import TransactionDrawer from '../dashboard/components/TransactionDrawer';
import { CategoryMergeDialog } from './components/CategoryMergeDialog';
import { getTransactions } from '../transactions/services/TransactionsService';
import { mergeCategories } from './services/CategoryService';
import type { Category } from './models/Category';
import type { CategoryStatistic } from './models/CategoryStatistics';
import type { Transaction } from '../transactions/models/Transaction';

type SortField = 'name' | 'transactionCount' | 'totalAmount';
type SortDirection = 'asc' | 'desc';

export default function CategoriesPage() {
    const { accountId } = useAccount();
    const { categories, loading, error, refreshCategories } = useCategories(accountId || 0);
    const { statistics, isLoading: statsLoading } = useCategoryStatistics(accountId || null, 'all');

    const [searchQuery, setSearchQuery] = useState('');
    const [sortField, setSortField] = useState<SortField>('name');
    const [sortDirection, setSortDirection] = useState<SortDirection>('asc');

    // Transaction drawer state
    const [isDrawerOpen, setIsDrawerOpen] = useState(false);
    const [drawerTransactions, setDrawerTransactions] = useState<Transaction[]>([]);
    const [isLoadingTransactions, setIsLoadingTransactions] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState<Category | null>(null);

    // Merge dialog state
    const [isMergeDialogOpen, setIsMergeDialogOpen] = useState(false);
    const [categoryToMerge, setCategoryToMerge] = useState<Category | null>(null);

    // Combineer categorieën met hun statistieken
    const categoriesWithStats = useMemo(() => {
        if (!statistics?.categories) return [];

        return categories.map(category => {
            const stats = statistics.categories.find(s => s.categoryId === category.id);
            return {
                category,
                stats: stats || null
            };
        });
    }, [categories, statistics]);

    // Filter op zoekopdracht
    const filteredCategories = useMemo(() => {
        return categoriesWithStats.filter(item =>
            item.category.name.toLowerCase().includes(searchQuery.toLowerCase())
        );
    }, [categoriesWithStats, searchQuery]);

    // Sorteer categorieën
    const sortedCategories = useMemo(() => {
        const sorted = [...filteredCategories];

        sorted.sort((a, b) => {
            let compareValue = 0;

            switch (sortField) {
                case 'name':
                    compareValue = a.category.name.localeCompare(b.category.name);
                    break;
                case 'transactionCount':
                    compareValue = (a.stats?.transactionCount || 0) - (b.stats?.transactionCount || 0);
                    break;
                case 'totalAmount':
                    compareValue = Math.abs(a.stats?.totalAmount || 0) - Math.abs(b.stats?.totalAmount || 0);
                    break;
            }

            return sortDirection === 'asc' ? compareValue : -compareValue;
        });

        return sorted;
    }, [filteredCategories, sortField, sortDirection]);

    // Groepeer categorieën per budget
    const categoriesByBudget = useMemo(() => {
        const groups = new Map<string, typeof sortedCategories>();

        sortedCategories.forEach(item => {
            const budgetKey = item.category.budgetName || 'Geen budget';
            if (!groups.has(budgetKey)) {
                groups.set(budgetKey, []);
            }
            groups.get(budgetKey)!.push(item);
        });

        // Sorteer de budget groepen: eerst budgetten met naam, dan "Geen budget"
        const sortedGroups = Array.from(groups.entries()).sort((a, b) => {
            if (a[0] === 'Geen budget') return 1;
            if (b[0] === 'Geen budget') return -1;
            return a[0].localeCompare(b[0]);
        });

        return sortedGroups;
    }, [sortedCategories]);

    const handleSortChange = (field: SortField) => {
        if (sortField === field) {
            // Toggle direction if same field
            setSortDirection(prev => prev === 'asc' ? 'desc' : 'asc');
        } else {
            setSortField(field);
            setSortDirection('asc');
        }
    };

    const handleCategoryClick = async (category: Category) => {
        if (!accountId) return;

        setSelectedCategory(category);
        setIsDrawerOpen(true);
        setIsLoadingTransactions(true);

        try {
            // Fetch all transactions for this category (no date filter for now - shows all time)
            const response = await getTransactions(accountId, undefined, undefined);

            // Filter by category
            const filteredTransactions = response.data.filter(t => t.category?.id === category.id);
            setDrawerTransactions(filteredTransactions);
        } catch (error) {
            console.error('Error fetching transactions:', error);
            setDrawerTransactions([]);
        } finally {
            setIsLoadingTransactions(false);
        }
    };

    const handleMergeClick = (category: Category) => {
        setCategoryToMerge(category);
        setIsMergeDialogOpen(true);
    };

    const handleMerge = async (sourceId: number, targetId: number) => {
        if (!accountId) return;
        await mergeCategories(accountId, sourceId, targetId);
        await refreshCategories();
    };

    if (loading || statsLoading) {
        return (
            <div className="flex justify-center items-center min-h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <p>Fout bij het laden van categorieën: {error}</p>
                <button
                    onClick={refreshCategories}
                    className="mt-2 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                >
                    Opnieuw proberen
                </button>
            </div>
        );
    }

    if (!accountId) {
        return (
            <div className="text-center py-12">
                <p className="text-gray-600">Geen account geselecteerd</p>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-bold text-gray-900">Categorieën</h1>
                    <p className="text-gray-600 mt-1">
                        Beheer je categorieën, bewerk, verwijder of voeg samen
                    </p>
                </div>
                <button
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors font-medium"
                    onClick={() => {
                        // TODO: Implement create category modal
                        alert('Nieuwe categorie functionaliteit komt in Fase 4');
                    }}
                >
                    + Nieuwe categorie
                </button>
            </div>

            {/* Search and Filter Bar */}
            <div className="bg-white rounded-lg shadow p-4">
                <div className="flex gap-4 items-center">
                    <div className="flex-1">
                        <div className="relative">
                            <input
                                type="text"
                                placeholder="Zoeken op naam..."
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                            <svg
                                className="absolute right-3 top-2.5 h-5 w-5 text-gray-400"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                        </div>
                    </div>

                    {/* Sort Controls */}
                    <div className="flex gap-2">
                        <button
                            onClick={() => handleSortChange('name')}
                            className={`px-3 py-2 rounded-lg border transition-colors ${
                                sortField === 'name'
                                    ? 'bg-blue-100 border-blue-300 text-blue-700'
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            Naam {sortField === 'name' && (sortDirection === 'asc' ? '↑' : '↓')}
                        </button>
                        <button
                            onClick={() => handleSortChange('transactionCount')}
                            className={`px-3 py-2 rounded-lg border transition-colors ${
                                sortField === 'transactionCount'
                                    ? 'bg-blue-100 border-blue-300 text-blue-700'
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            Aantal {sortField === 'transactionCount' && (sortDirection === 'asc' ? '↑' : '↓')}
                        </button>
                        <button
                            onClick={() => handleSortChange('totalAmount')}
                            className={`px-3 py-2 rounded-lg border transition-colors ${
                                sortField === 'totalAmount'
                                    ? 'bg-blue-100 border-blue-300 text-blue-700'
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            Bedrag {sortField === 'totalAmount' && (sortDirection === 'asc' ? '↑' : '↓')}
                        </button>
                    </div>
                </div>

                {/* Results count */}
                <div className="mt-3 text-sm text-gray-600">
                    {filteredCategories.length} van {categories.length} categorie{categories.length !== 1 ? 'ën' : ''}
                </div>
            </div>

            {/* Category List Grouped by Budget */}
            <div className="space-y-6">
                {categoriesByBudget.map(([budgetName, categories]) => (
                    <div key={budgetName} className="space-y-3">
                        {/* Budget Header */}
                        <div className="border-b-2 border-gray-300 pb-2">
                            <h2 className="text-xl font-semibold text-gray-800">
                                {budgetName}
                            </h2>
                            <p className="text-sm text-gray-600">
                                {categories.length} categorie{categories.length !== 1 ? 'ën' : ''}
                            </p>
                        </div>

                        {/* Categories in this budget */}
                        <CategoryList
                            categories={categories}
                            onRefresh={refreshCategories}
                            onCategoryClick={handleCategoryClick}
                            onMergeClick={handleMergeClick}
                        />
                    </div>
                ))}
            </div>

            {/* Transaction Drawer */}
            {selectedCategory && (
                <TransactionDrawer
                    isOpen={isDrawerOpen}
                    onClose={() => setIsDrawerOpen(false)}
                    categoryName={selectedCategory.name}
                    categoryColor={selectedCategory.color}
                    monthYear="Alle transacties"
                    transactions={drawerTransactions}
                    isLoading={isLoadingTransactions}
                />
            )}

            {/* Merge Dialog */}
            {accountId && (
                <CategoryMergeDialog
                    isOpen={isMergeDialogOpen}
                    sourceCategory={categoryToMerge}
                    categories={categories}
                    accountId={accountId}
                    onClose={() => setIsMergeDialogOpen(false)}
                    onMerge={handleMerge}
                />
            )}
        </div>
    );
}
