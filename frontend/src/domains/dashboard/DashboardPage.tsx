import { useState, useEffect, useCallback } from 'react';
import { useAccount } from '../../app/context/AccountContext';
import { useFeatureFlag } from '../../shared/contexts/FeatureFlagContext';
import { useTransactions } from '../transactions/hooks/useTransactions';
import { useMonthlyStatistics } from '../transactions/hooks/useMonthlyStatistics';
import CompactTransactionChart from './components/CompactTransactionChart';
import InsightsPanel from './components/InsightsPanel';
import BudgetOverviewCard from './components/BudgetOverviewCard';
import PeriodPicker from '../transactions/components/PeriodPicker';
import { Toaster } from 'react-hot-toast';
import { fetchActiveBudgets, fetchOlderBudgets, fetchProjects } from '../budgets/services/AdaptiveDashboardService';
import type { ActiveBudget, OlderBudget, ProjectDetails } from '../budgets/models/AdaptiveBudget';
import ActiveBudgetsGrid from './components/ActiveBudgetsGrid';
import OlderBudgetsPanel from './components/OlderBudgetsPanel';
import ProjectsSection from '../budgets/components/ProjectsSection';
import ProjectCreateForm from '../budgets/components/ProjectCreateForm';
import SavingsAccountsPanel from './components/SavingsAccountsPanel';
import AccountService from '../accounts/services/AccountService';
import type { Account } from '../accounts/models/Account';
import { formatMoney } from '../../shared/utils/MoneyFormat';

const formatPeriod = (startDate: string, endDate: string): string => {
    const start = new Date(startDate);
    const end = new Date(endDate);

    const monthNames = [
        'Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
        'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'
    ];

    // Check if same month and year
    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
        return `${monthNames[start.getMonth()]} ${start.getFullYear()}`;
    }

    // Different months or years - show range
    const startDay = start.getDate();
    const endDay = end.getDate();
    const startMonth = monthNames[start.getMonth()];
    const endMonth = monthNames[end.getMonth()];
    const startYear = start.getFullYear();
    const endYear = end.getFullYear();

    if (startYear === endYear) {
        if (start.getMonth() === end.getMonth()) {
            return `${startDay}-${endDay} ${startMonth} ${startYear}`;
        }
        return `${startDay} ${startMonth} - ${endDay} ${endMonth} ${startYear}`;
    }

    return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
};

export default function DashboardPage() {
    const { accountId } = useAccount();
    const livingDashboardEnabled = useFeatureFlag('living_dashboard');
    const projectsEnabled = useFeatureFlag('projects');

    const [currentMonth, setCurrentMonth] = useState<string>(new Date().toISOString().substring(0, 7));

    // Adaptive dashboard state
    const [activeBudgets, setActiveBudgets] = useState<ActiveBudget[]>([]);
    const [olderBudgets, setOlderBudgets] = useState<OlderBudget[]>([]);
    const [projects, setProjects] = useState<ProjectDetails[]>([]);
    const [accounts, setAccounts] = useState<Account[]>([]);
    const [isLoadingAdaptive, setIsLoadingAdaptive] = useState(false);
    const [isCreateProjectModalOpen, setIsCreateProjectModalOpen] = useState(false);

    // Use transaction hook directly for period control
    const {
        months,
        startDate,
        endDate,
        setStartDate,
        setEndDate,
        summary,
        transactions,
        refresh: _refresh,
    } = useTransactions();
    void _refresh; // Mark as intentionally unused

    const { statistics } = useMonthlyStatistics(accountId, 12);

    // Calculate legacy insights
    const [insights, setInsights] = useState<Array<{
        type: 'warning' | 'success' | 'info' | 'tip';
        message: string;
        icon: string;
    }>>([]);

    useEffect(() => {
        if (!summary || !statistics) return;

        const newInsights = [];

        // Uncategorized transactions warning
        const uncategorized = transactions.filter(t => !t.category).length;
        if (uncategorized > 0) {
            newInsights.push({
                type: 'info' as const,
                message: `${uncategorized} transactie${uncategorized !== 1 ? 's' : ''} wacht${uncategorized === 1 ? '' : 'en'} op categorisatie`,
                icon: '📍'
            });
        }

        // Compare current month to average
        if (statistics.trimmedMean > 0) {
            const currentMonthExpenses = Math.abs(Number(summary.total_debit));
            const percentageDiff = ((currentMonthExpenses - statistics.trimmedMean) / statistics.trimmedMean) * 100;

            if (percentageDiff > 20) {
                newInsights.push({
                    type: 'warning' as const,
                    message: `Je uitgaven liggen ${percentageDiff.toFixed(0)}% hoger dan gemiddeld deze maand`,
                    icon: '⚠️'
                });
            } else if (percentageDiff < -10) {
                newInsights.push({
                    type: 'success' as const,
                    message: `Goed bezig! Je uitgaven liggen ${Math.abs(percentageDiff).toFixed(0)}% lager dan gemiddeld`,
                    icon: '✅'
                });
            }
        }

        // Positive net total
        const netTotal = Number(summary.net_total);
        if (netTotal > 0) {
            newInsights.push({
                type: 'success' as const,
                message: `Je hebt deze periode ${formatMoney(Math.abs(netTotal))} gespaard!`,
                icon: '💰'
            });
        }

        // Tip for pattern matching
        if (uncategorized > 10) {
            newInsights.push({
                type: 'tip' as const,
                message: 'Tip: Maak patronen aan om transacties automatisch te categoriseren',
                icon: '💡'
            });
        }

        setInsights(newInsights);
    }, [summary, statistics, transactions]);

    const loadAdaptiveDashboard = useCallback(async () => {
        if (!accountId) return;

        setIsLoadingAdaptive(true);
        try {
            // Fetch active budgets (EXPENSE/INCOME with insights) using selected period
            const activeData = await fetchActiveBudgets(2, undefined, startDate, endDate, accountId);
            setActiveBudgets(activeData);

            // Fetch older budgets
            const olderData = await fetchOlderBudgets(2, undefined, accountId);
            setOlderBudgets(olderData);

            // Fetch projects if feature enabled
            if (projectsEnabled) {
                const projectsData = await fetchProjects(undefined, accountId); // Fetch all projects (ACTIVE + COMPLETED)
                setProjects(projectsData);
            }

            // Fetch accounts (including savings accounts)
            const accountsData = await AccountService.getAll();
            setAccounts(accountsData);
        } catch (error) {
            console.error('[Dashboard] Error loading adaptive dashboard:', error);
        } finally {
            setIsLoadingAdaptive(false);
        }
    }, [accountId, startDate, endDate, projectsEnabled]);

    // Fetch adaptive dashboard data when living_dashboard is enabled or period changes
    useEffect(() => {
        if (livingDashboardEnabled && accountId && startDate && endDate) {
            loadAdaptiveDashboard();
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [livingDashboardEnabled, accountId, startDate, endDate]);

    // Handle period change from PeriodPicker
    const handlePeriodChange = useCallback((start: string, end: string) => {
        setStartDate(start);
        setEndDate(end);
        // Extract month from start date (YYYY-MM format)
        setCurrentMonth(start.substring(0, 7));
    }, []);

    if (!accountId) {
        return (
            <div className="flex items-center justify-center min-h-96">
                <div className="text-center">
                    <p className="text-gray-600 text-lg">Geen account geselecteerd</p>
                    <p className="text-gray-500 text-sm mt-2">Selecteer een account om het dashboard te bekijken</p>
                </div>
            </div>
        );
    }

    if (!livingDashboardEnabled && !summary) {
        return (
            <div className="flex items-center justify-center min-h-96">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Dashboard wordt geladen...</p>
                </div>
            </div>
        );
    }

    // Render adaptive dashboard if feature flag is enabled
    if (livingDashboardEnabled) {
        // Separate EXPENSE and INCOME budgets
        const expenseBudgets = activeBudgets.filter(b => b.budgetType === 'EXPENSE');
        const incomeBudgets = activeBudgets.filter(b => b.budgetType === 'INCOME');

        // Calculate totals for each budget type
        const totalIncome = incomeBudgets.reduce((sum, b) => {
            const current = Math.abs(parseFloat(b.insight?.current || '0'));
            return sum + current;
        }, 0);

        const totalExpense = expenseBudgets.reduce((sum, b) => {
            const current = Math.abs(parseFloat(b.insight?.current || '0'));
            return sum + current;
        }, 0);

        const formatAmount = (amount: number) => formatMoney(amount);

        return (
            <div className="min-h-screen bg-gray-50 p-4 md:p-6">
                <Toaster position="top-center" />
                <header className="flex flex-wrap justify-between items-center gap-2 md:gap-4 mb-4">
                    <h1 className="text-xl md:text-2xl font-bold">Dashboard</h1>
                </header>

                {/* Full Width Chart - Collapsible */}
                <div className="mb-8">
                    {summary?.daily && summary.daily.length > 0 && (
                        <details className="bg-white rounded-lg shadow">
                            <summary className="cursor-pointer p-6 font-semibold text-gray-800 hover:bg-gray-50 transition-colors select-none list-none [&::-webkit-details-marker]:hidden">
                                <div className="flex items-center gap-2">
                                    <span className="text-gray-400" aria-hidden="true">▶</span>
                                    <span>Balans Overzicht (Huidige Periode)</span>
                                </div>
                            </summary>
                            <div className="p-6 pt-0">
                                <CompactTransactionChart
                                    data={summary.daily}
                                    title=""
                                    height={400}
                                />
                            </div>
                        </details>
                    )}
                </div>

                <div className="mb-6 md:mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    {summary?.end_balance && (
                        <div className="flex items-center gap-2 bg-white px-3 py-2 rounded-lg shadow-sm border border-gray-200">
                            <span className="text-sm text-gray-600">Saldo:</span>
                            <span className="text-base md:text-lg font-bold text-blue-600">
                                {formatMoney(Number(summary.end_balance))}
                            </span>
                        </div>
                    )}
                    {!summary?.end_balance && <div />}
                    <PeriodPicker
                        months={months}
                        onChange={handlePeriodChange}
                    />
                </div>

                {isLoadingAdaptive ? (
                    <div className="mb-8">
                        <div className="bg-white rounded-lg shadow p-6">
                            <div className="flex items-center justify-center min-h-96">
                                <div className="text-center">
                                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                                    <p className="text-gray-600">Dashboard wordt geladen...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                ) : (
                    <>
                        {/* Income Budgets Section */}
                        {incomeBudgets.length > 0 && (
                            <div className="mb-6 md:mb-8">
                                <div className="bg-gradient-to-br from-green-50 to-emerald-50 rounded-lg shadow-md border-2 border-green-200 p-4 md:p-6">
                                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 mb-4">
                                        <div className="flex flex-wrap items-baseline gap-2 md:gap-4">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xl md:text-2xl">💰</span>
                                                <h2 className="text-xl md:text-2xl font-bold text-green-900">
                                                    Inkomsten
                                                </h2>
                                            </div>
                                            <span className="text-sm text-green-700 font-medium hidden sm:inline">
                                                {startDate && endDate ? formatPeriod(startDate, endDate) : ''}
                                            </span>
                                            <span className="text-base md:text-lg font-bold text-green-800">
                                                {formatAmount(totalIncome)}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs sm:text-sm text-green-700 font-medium">
                                                {incomeBudgets.length} {incomeBudgets.length === 1 ? 'budget' : 'budgetten'}
                                            </span>
                                        </div>
                                    </div>
                                    <ActiveBudgetsGrid
                                        budgets={incomeBudgets}
                                        accountId={accountId}
                                        startDate={startDate ?? undefined}
                                        endDate={endDate ?? undefined}
                                    />
                                </div>
                            </div>
                        )}

                        {/* Expense Budgets Section */}
                        {expenseBudgets.length > 0 && (
                            <div className="mb-6 md:mb-8">
                                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-lg shadow-md border-2 border-blue-200 p-4 md:p-6">
                                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-2 mb-4">
                                        <div className="flex flex-wrap items-baseline gap-2 md:gap-4">
                                            <div className="flex items-center gap-2">
                                                <span className="text-xl md:text-2xl">💸</span>
                                                <h2 className="text-xl md:text-2xl font-bold text-blue-900">
                                                    Uitgaven
                                                </h2>
                                            </div>
                                            <span className="text-sm text-blue-700 font-medium hidden sm:inline">
                                                {startDate && endDate ? formatPeriod(startDate, endDate) : ''}
                                            </span>
                                            <span className="text-base md:text-lg font-bold text-blue-800">
                                                {formatAmount(totalExpense)}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-xs sm:text-sm text-blue-700 font-medium">
                                                {expenseBudgets.length} {expenseBudgets.length === 1 ? 'budget' : 'budgetten'}
                                            </span>
                                        </div>
                                    </div>
                                    <ActiveBudgetsGrid
                                        budgets={expenseBudgets}
                                        accountId={accountId}
                                        startDate={startDate ?? undefined}
                                        endDate={endDate ?? undefined}
                                    />
                                </div>
                            </div>
                        )}
                    </>
                )}

                {/* Savings Accounts Section */}
                <div className="mb-6 md:mb-8">
                    <SavingsAccountsPanel
                        accounts={accounts}
                        checkingAccountId={accountId}
                    />
                </div>

                {/* Projects Section (if enabled) */}
                {projectsEnabled && (
                    <div className="mb-6 md:mb-8">
                        <ProjectsSection
                            projects={projects}
                            onCreateProject={() => setIsCreateProjectModalOpen(true)}
                        />
                    </div>
                )}

                {/* Project Create Modal */}
                {accountId && (
                    <ProjectCreateForm
                        isOpen={isCreateProjectModalOpen}
                        onClose={() => setIsCreateProjectModalOpen(false)}
                        accountId={accountId}
                        onSuccess={() => loadAdaptiveDashboard()}
                    />
                )}

                {/* Older Budgets Panel (collapsible) */}
                <div className="mb-6 md:mb-8">
                    <OlderBudgetsPanel budgets={olderBudgets} accountId={accountId} />
                </div>

                <div className="mb-6 md:mb-8 flex justify-end">
                    <PeriodPicker
                        months={months}
                        onChange={handlePeriodChange}
                    />
                </div>
            </div>
        );
    }

    // Fallback to original dashboard if feature flag is disabled
    return (
        <div className="min-h-screen bg-gray-50 p-4 md:p-6">
            <Toaster position="top-center" />

            {/* Header with Period Picker */}
            <header className="flex flex-col sm:flex-row flex-wrap justify-between items-start sm:items-center gap-2 md:gap-4 mb-4">
                <div className="flex flex-wrap items-baseline gap-2 md:gap-4">
                    <h1 className="text-xl md:text-2xl font-bold">Dashboard</h1>
                    {summary?.end_balance && (
                        <span className="text-base md:text-lg font-semibold text-blue-600">
                            Saldo: {formatMoney(Number(summary.end_balance))}
                        </span>
                    )}
                </div>
                <PeriodPicker
                    months={months}
                    onChange={handlePeriodChange}
                />
            </header>

            {/* Full Width Chart - Collapsible */}
            <div className="mb-8">
                {summary.daily && summary.daily.length > 0 && (
                    <details className="bg-white rounded-lg shadow">
                        <summary className="cursor-pointer p-6 font-semibold text-gray-800 hover:bg-gray-50 transition-colors select-none list-none [&::-webkit-details-marker]:hidden">
                            <div className="flex items-center gap-2">
                                <span className="text-gray-400" aria-hidden="true">▶</span>
                                <span>Balans Overzicht (Huidige Periode)</span>
                            </div>
                        </summary>
                        <div className="p-6 pt-0">
                            <CompactTransactionChart
                                data={summary.daily}
                                title=""
                                height={400}
                            />
                        </div>
                    </details>
                )}
            </div>

            {/* Budget Overview - Full Width */}
            <div className="mb-8">
                <BudgetOverviewCard
                    accountId={accountId}
                    monthYear={currentMonth}
                />
            </div>

            {/* Insights - Full Width */}
            {insights.length > 0 && (
                <div className="mb-8">
                    <InsightsPanel insights={insights} />
                </div>
            )}
        </div>
    );
}
