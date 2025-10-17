import { useState, useEffect } from 'react';
import { useAccount } from '../../app/context/AccountContext';
import { useDashboardData } from './hooks/useDashboardData';
import CompactTransactionChart from './components/CompactTransactionChart';
import InsightsPanel from './components/InsightsPanel';
import BudgetOverviewCard from './components/BudgetOverviewCard';
import PeriodPicker from '../transactions/components/PeriodPicker';
import { Toaster } from 'react-hot-toast';
import { fetchAvailableMonths } from '../../lib/api';

export default function DashboardPage() {
    const { accountId } = useAccount();
    const [availableMonths, setAvailableMonths] = useState<string[]>([]);
    const [startDate, setStartDate] = useState<string>('');
    const [endDate, setEndDate] = useState<string>('');
    const [currentMonth, setCurrentMonth] = useState<string>(new Date().toISOString().substring(0, 7));

    const {
        summary,
        insights,
    } = useDashboardData(accountId);

    // Fetch available months when account changes
    useEffect(() => {
        if (accountId) {
            fetchAvailableMonths(accountId).then(months => {
                setAvailableMonths(months);
            });
        }
    }, [accountId]);

    // Handle period change from PeriodPicker
    const handlePeriodChange = (start: string, end: string) => {
        setStartDate(start);
        setEndDate(end);
        // Extract month from start date (YYYY-MM format)
        setCurrentMonth(start.substring(0, 7));
    };

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

    if (!summary) {
        return (
            <div className="flex items-center justify-center min-h-96">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Dashboard wordt geladen...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <Toaster position="top-center" />

            {/* Header with Period Picker */}
            <header className="flex flex-wrap justify-between items-center gap-4 mb-4">
                <h1 className="text-2xl font-bold">Dashboard</h1>
                <PeriodPicker
                    months={availableMonths}
                    onChange={handlePeriodChange}
                />
            </header>

            {/* Full Width Chart */}
            <div className="mb-8">
                {summary.daily && summary.daily.length > 0 && (
                    <CompactTransactionChart
                        data={summary.daily}
                        title="Balans Overzicht (Huidige Periode)"
                        height={400}
                    />
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
