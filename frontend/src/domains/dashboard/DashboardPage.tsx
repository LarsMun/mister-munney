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
        refresh,
    } = useTransactions();

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
                message: `Je hebt deze periode ${Math.abs(netTotal).toFixed(2)} euro gespaard!`,
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
        // Separate EXPENSE/INCOME budgets from PROJECTS
        const expenseIncomeBudgets = activeBudgets.filter(
            b => b.budgetType === 'EXPENSE' || b.budgetType === 'INCOME'
        );

        return (
            <div className="min-h-screen bg-gray-50 p-6">
                <Toaster position="top-center" />

                {/* Header with Period Picker */}
                <header className="flex flex-wrap justify-between items-center gap-4 mb-4">
                    <h1 className="text-2xl font-bold">Dashboard</h1>
                    <PeriodPicker
                        months={months}
                        onChange={handlePeriodChange}
                    />
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

                {/* Active Budgets Grid (EXPENSE/INCOME only) */}
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
                ) : expenseIncomeBudgets.length > 0 && (
                    <div className="mb-8">
                        <div className="bg-white rounded-lg shadow p-6">
                            <div className="flex justify-between items-center mb-4">
                                <h2 className="text-2xl font-bold text-gray-900">
                                    {startDate && endDate ? formatPeriod(startDate, endDate) : ''}
                                </h2>
                                <h3 className="text-lg font-semibold text-gray-800">
                                    Actieve Budgetten ({expenseIncomeBudgets.length})
                                </h3>
                            </div>
                            <ActiveBudgetsGrid
                                budgets={expenseIncomeBudgets}
                                accountId={accountId}
                                startDate={startDate ?? undefined}
                                endDate={endDate ?? undefined}
                            />
                        </div>
                    </div>
                )}

                {/* Projects Section (if enabled) */}
                {projectsEnabled && (
                    <div className="mb-8">
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
                <div className="mb-8">
                    <OlderBudgetsPanel budgets={olderBudgets} />
                </div>
            </div>
        );
    }

    // Fallback to original dashboard if feature flag is disabled
    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <Toaster position="top-center" />

            {/* Header with Period Picker */}
            <header className="flex flex-wrap justify-between items-center gap-4 mb-4">
                <h1 className="text-2xl font-bold">Dashboard</h1>
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
