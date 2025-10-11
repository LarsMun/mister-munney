import { useState } from 'react';
import { useAccount } from '../../app/context/AccountContext';
import { useDashboardData } from './hooks/useDashboardData';
import HeroSection from './components/HeroSection';
import QuickStatsGrid from './components/QuickStatsGrid';
import CompactTransactionChart from './components/CompactTransactionChart';
import MonthlyStatisticsCard from '../transactions/components/MonthlyStatisticsCard';
import QuickActions from './components/QuickActions';
import InsightsPanel from './components/InsightsPanel';
import { Toaster } from 'react-hot-toast';

export default function DashboardPage() {
    const { accountId } = useAccount();
    const {
        summary,
        statistics,
        insights,
        importTransactions,
    } = useDashboardData(accountId);

    const [showUpload, setShowUpload] = useState(false);

    const handleImportClick = () => {
        setShowUpload(true);
    };

    const handleFileUpload = (file: File) => {
        importTransactions(file);
        setShowUpload(false);
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

    const currentBalance = Number(summary.end_balance);
    const monthlyChange = Number(summary.net_total);
    const averageBalance = statistics?.trimmedMean || Number(summary.total_debit);

    return (
        <div className="min-h-screen bg-gray-50 pb-8">
            <Toaster position="top-center" />
            
            {/* Hero Section */}
            <HeroSection
                currentBalance={currentBalance}
                monthlyChange={monthlyChange}
                averageBalance={averageBalance}
            />

            {/* Quick Stats */}
            <div className="mb-8">
                <QuickStatsGrid
                    startBalance={Number(summary.start_balance)}
                    endBalance={Number(summary.end_balance)}
                    totalDebit={Number(summary.total_debit)}
                    totalCredit={Number(summary.total_credit)}
                    netTotal={Number(summary.net_total)}
                    transactionCount={summary.total}
                />
            </div>

            {/* Two Column Layout */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                {/* Left Column - Charts */}
                <div className="lg:col-span-2 space-y-6">
                    {summary.daily && summary.daily.length > 0 && (
                        <CompactTransactionChart
                            data={summary.daily}
                            title="Balans Overzicht (Huidige Periode)"
                            height={350}
                        />
                    )}
                    
                    {statistics && (
                        <MonthlyStatisticsCard
                            statistics={statistics}
                            isLoading={false}
                            error={null}
                        />
                    )}
                </div>

                {/* Right Column - Actions & Insights */}
                <div className="space-y-6">
                    <QuickActions
                        onImportTransactions={handleImportClick}
                        uncategorizedCount={insights.find(i => i.message.includes('categorisatie'))
                            ? parseInt(insights.find(i => i.message.includes('categorisatie'))!.message.match(/\d+/)?.[0] || '0')
                            : 0
                        }
                    />
                    
                    {insights.length > 0 && (
                        <InsightsPanel insights={insights} />
                    )}
                </div>
            </div>

            {/* Upload Modal */}
            {showUpload && (
                <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-semibold">CSV uploaden</h2>
                            <button 
                                onClick={() => setShowUpload(false)} 
                                className="text-gray-500 hover:text-black"
                            >
                                ✕
                            </button>
                        </div>
                        <input
                            type="file"
                            accept=".csv"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    handleFileUpload(file);
                                }
                            }}
                            className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
