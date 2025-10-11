// frontend/src/domains/budgets/BudgetsPage.tsx

import { useState, useMemo } from 'react';
import { useBudgets } from './hooks/useBudgets';
import { BudgetCard } from './components/BudgetCard';
import { CreateBudgetModal } from './components/CreateBudgetModal';
import { AvailableCategories } from './components/AvailableCategories';
import { useAccount } from '../../app/context/AccountContext';
import ConfirmDialog from '../../shared/components/ConfirmDialog';
import { isCurrentlyActive, isFuture, isExpired } from './services/BudgetsService';
import { useMonthlyStatistics } from '../transactions/hooks/useMonthlyStatistics';
import MonthlyStatisticsCard from '../transactions/components/MonthlyStatisticsCard';
import { useCategoryStatistics } from '../categories/hooks/useCategoryStatistics';
import type { Budget } from './models/Budget';

export default function BudgetsPage() {
    const { accountId } = useAccount();
    const {
        budgets,
        availableCategories,
        isLoading,
        error,
        createBudget,
        updateBudget,
        deleteBudget,
        createBudgetVersion,
        updateBudgetVersion,
        deleteBudgetVersion,
        assignCategories,
        removeCategory,
        refresh
    } = useBudgets();

    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [budgetToDelete, setBudgetToDelete] = useState<number | null>(null);

    //Statistics
    const [statisticsMonths, setStatisticsMonths] = useState<string | number>('all');
    const { statistics, isLoading: statsLoading, error: statsError } = useMonthlyStatistics(
        accountId || null,
        statisticsMonths
    );

    const {
        statistics: categoryStats
    } = useCategoryStatistics(accountId || null, 'all');

    // Groepeer budgetten op status
    const { activeBudgets, futureBudgets, expiredBudgets } = useMemo(() => {
        const active = budgets.filter(isCurrentlyActive);
        const future = budgets.filter(isFuture);
        const expired = budgets.filter(isExpired);

        return {
            activeBudgets: active,
            futureBudgets: future,
            expiredBudgets: expired
        };
    }, [budgets]);

    const handleCategoryDrop = async (budgetId: number, droppedCategoryIds: number[]) => {
        // Vind het budget om de bestaande categorieën te krijgen
        const budget = budgets.find(b => b.id === budgetId);
        if (!budget) return;

        // Onthoud scroll positie
        const scrollPosition = window.scrollY;

        // Combineer bestaande + nieuwe categorieën (zonder duplicaten)
        const existingCategoryIds = budget.categories.map(c => c.id);
        const allCategoryIds = [...new Set([...existingCategoryIds, ...droppedCategoryIds])];

        await assignCategories(budgetId, { categoryIds: allCategoryIds });

        // Herstel scroll positie na een klein moment (wacht op re-render)
        requestAnimationFrame(() => {
            window.scrollTo({ top: scrollPosition, behavior: 'instant' });
        });
    };

    const handleRemoveCategory = async (budgetId: number, categoryId: number) => {
        const scrollPosition = window.scrollY;

        await removeCategory(budgetId, categoryId);

        requestAnimationFrame(() => {
            window.scrollTo({ top: scrollPosition, behavior: 'instant' });
        });
    };

    const handleDeleteBudgetClick = (budgetId: number) => {
        setBudgetToDelete(budgetId);
    };

    const handleConfirmDeleteBudget = async () => {
        if (budgetToDelete !== null) {
            await deleteBudget(budgetToDelete);
            setBudgetToDelete(null);
        }
    };

    if (isLoading) {
        return (
            <div className="flex justify-center items-center min-h-64">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <p>Fout bij het laden van budgetten: {error}</p>
                <button
                    onClick={refresh}
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

    const BudgetSection = ({
                               title,
                               budgets,
                               statusColor
                           }: {
        title: string;
        budgets: Budget[];
        statusColor: string;
    }) => {
        if (budgets.length === 0) return null;

        return (
            <div className="space-y-3">
                <div className="flex items-center space-x-3">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    <span className={`text-sm px-3 py-1 rounded-full ${statusColor}`}>
                        {budgets.length}
                    </span>
                </div>
                <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    {budgets.map((budget) => (
                        <BudgetCard
                            key={budget.id}
                            budget={budget}
                            categoryStats={categoryStats}
                            onUpdate={updateBudget}
                            onDelete={handleDeleteBudgetClick}
                            onDrop={handleCategoryDrop}
                            onRemoveCategory={handleRemoveCategory}
                            onCreateVersion={createBudgetVersion}
                            onUpdateVersion={updateBudgetVersion}
                            onDeleteVersion={deleteBudgetVersion}
                        />
                    ))}
                </div>
            </div>
        );
    };

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex justify-between items-center">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Budgetbeheer</h1>
                    <p className="text-gray-600 mt-1">
                        Beheer je budgetten en wijs categorieën toe
                    </p>
                </div>
                <button
                    onClick={() => setIsCreateModalOpen(true)}
                    className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors"
                >
                    + Nieuw Budget
                </button>
            </div>

            <MonthlyStatisticsCard
                statistics={statistics}
                isLoading={statsLoading}
                error={statsError}
                onMonthsChange={setStatisticsMonths}
            />

            {/* Main Content */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Left Column: Available Categories - Sticky */}
                <div className="lg:col-span-1">
                    <div className="lg:sticky lg:top-4">
                        <AvailableCategories
                            categories={availableCategories.filter(cat => {
                                // Filter credit-only categorieën uit
                                const stats = categoryStats?.categories.find(s => s.categoryId === cat.id);
                                // Alleen tonen als netto uitgave (negatief) of geen stats
                                return !stats || stats.medianLast12Months <= 0;
                            })}
                            categoryStats={categoryStats}
                            onRefresh={refresh}
                        />
                    </div>
                </div>

                {/* Right Column: Budgets */}
                <div className="lg:col-span-2">
                    {budgets.length === 0 ? (
                        <div className="text-center py-12 bg-white rounded-lg shadow-md">
                            <div className="text-6xl mb-4">💰</div>
                            <h3 className="text-xl font-medium text-gray-900 mb-2">
                                Geen budgetten gevonden
                            </h3>
                            <p className="text-gray-600 mb-4">
                                Maak je eerste budget aan om je uitgaven te beheren
                            </p>
                            <button
                                onClick={() => setIsCreateModalOpen(true)}
                                className="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors"
                            >
                                Maak je eerste budget
                            </button>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Header met refresh knop */}
                            <div className="flex justify-between items-center">
                                <h2 className="text-lg font-semibold text-gray-900">
                                    Jouw Budgetten ({budgets.length})
                                </h2>
                                <button
                                    onClick={refresh}
                                    className="text-gray-500 hover:text-gray-700"
                                    title="Vernieuwen"
                                >
                                    🔄
                                </button>
                            </div>

                            {/* Lopende Budgetten */}
                            <BudgetSection
                                title="Lopende Budgetten"
                                budgets={activeBudgets}
                                statusColor="bg-green-100 text-green-800"
                            />

                            {/* Toekomstige Budgetten */}
                            <BudgetSection
                                title="Toekomstige Budgetten"
                                budgets={futureBudgets}
                                statusColor="bg-blue-100 text-blue-800"
                            />

                            {/* Verlopen Budgetten */}
                            <BudgetSection
                                title="Verlopen Budgetten"
                                budgets={expiredBudgets}
                                statusColor="bg-gray-100 text-gray-800"
                            />
                        </div>
                    )}
                </div>
            </div>

            {/* Create Budget Modal */}
            <CreateBudgetModal
                isOpen={isCreateModalOpen}
                onClose={() => setIsCreateModalOpen(false)}
                onCreate={createBudget}
                accountId={accountId}
            />

            {/* Delete Budget Confirm Dialog */}
            <ConfirmDialog
                open={budgetToDelete !== null}
                title="Budget verwijderen"
                description="Weet je zeker dat je dit budget wilt verwijderen? Deze actie kan niet ongedaan gemaakt worden."
                onConfirm={handleConfirmDeleteBudget}
                onCancel={() => setBudgetToDelete(null)}
            />
        </div>
    );
}
