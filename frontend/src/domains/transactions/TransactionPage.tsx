import { useState } from "react";
import { useTransactions } from './hooks/useTransactions';
import TransactionTable from './components/TransactionTable';
import TransactionChart from './components/TransactionChart';
import { Toaster } from 'react-hot-toast';
import { useAccount } from "../../app/context/AccountContext.tsx";
import SummaryBar from "./components/SummaryBar.tsx";
import TreeMapChartIncome from "./components/TreeMapChartIncome.tsx";
import TreeMapChartExpenses from "./components/TreeMapChartExpenses.tsx";
import FilterBar from "./components/FilterBar.tsx";
import PeriodPicker from "./components/PeriodPicker.tsx";
import { useMonthlyStatistics } from './hooks/useMonthlyStatistics';
import MonthlyStatisticsCard from './components/MonthlyStatisticsCard';

export default function TransactionPage() {
    const { accountId } = useAccount();
    const {
        months,
        startDate,
        setStartDate,
        setEndDate,
        summary,
        treeMapData,
        transactions,
        refresh,
        importTransactions,
    } = useTransactions();

    const [selection, setSelection] = useState<{ start: string | null; end: string | null }>({ start: null, end: null });
    const [finalSelection, setFinalSelection] = useState<{ start: string; end: string } | null>(null);

    const [statisticsMonths, setStatisticsMonths] = useState<string | number>('all');
    const { statistics, isLoading: statsLoading, error: statsError } = useMonthlyStatistics(
        accountId || null,
        statisticsMonths
    );

    const clearSelection = () => {
        setFinalSelection(null);
        setSelection({ start: null, end: null });
    };

    const [selectedCategoryNames, setSelectedCategoryNames] = useState<string[]>([]);

    const handleToggleCategory = (name: string) => {
        setSelectedCategoryNames(prev =>
            prev.includes(name)
                ? prev.filter(n => n !== name)
                : [...prev, name]
        );
    };

    // Filter transacties
    const filteredTransactions = transactions.filter(t => {
        if (selection.start && selection.end && (t.date < selection.start || t.date > selection.end)) {
            return false;
        }
        if (selectedCategoryNames.length > 0) {
            if (!t.category) return selectedCategoryNames.includes("Niet ingedeeld");
            if (!selectedCategoryNames.includes(t.category.name)) return false;
        }
        return true;
    });

    // Keuze welke grafiek actief is
    const [chartType, setChartType] = useState<'line' | 'treeMapDebit' | 'treeMapCredit'>('line');

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            <Toaster position="top-center" />

            <header className="flex flex-wrap justify-between items-center gap-4 mb-4">
                <h1 className="text-2xl font-bold">Transacties</h1>
                <PeriodPicker
                    months={months}
                    onChange={(newStartDate, newEndDate) => {
                        setStartDate(newStartDate);
                        setEndDate(newEndDate);
                    }}
                />
            </header>

            <div className="my-8">
                {/* Toggle knoppen voor grafieksoorten */}
                <div className="flex justify-start mb-4">
                    <button
                        className={`px-3 py-1 mx-2 text-xs ${chartType === 'line' ? 'bg-blue-500 text-white' : 'bg-gray-200'}`}
                        onClick={() => setChartType('line')}
                    >
                        Verloop
                    </button>
                    <button
                        className={`px-3 py-1 mx-2 text-xs ${chartType === 'treeMapDebit' ? 'bg-blue-500 text-white' : 'bg-gray-200'}`}
                        onClick={() => setChartType('treeMapDebit')}
                    >
                        Uitgaven
                    </button>
                    <button
                        className={`px-3 py-1 mx-2 text-xs ${chartType === 'treeMapCredit' ? 'bg-blue-500 text-white' : 'bg-gray-200'}`}
                        onClick={() => setChartType('treeMapCredit')}
                    >
                        Inkomsten
                    </button>
                </div>

                <div className="h-96">
                    {chartType === 'line' && (
                        <TransactionChart
                            data={summary?.daily || []}
                            onSelectRange={(start, end) => {
                                setSelection({ start, end });
                                if (start && end) {
                                    const s = start < end ? start : end;
                                    const e = start > end ? start : end;
                                    setFinalSelection({ start: s, end: e });
                                } else {
                                    setFinalSelection(null);
                                }
                            }}
                        />
                    )}
                    {chartType === 'treeMapDebit' && treeMapData && (
                        <TreeMapChartExpenses
                            treeMapData={treeMapData}
                            onSelectCategory={handleToggleCategory}
                        />
                    )}
                    {chartType === 'treeMapCredit' && treeMapData && (
                        <TreeMapChartIncome
                            categoryData={treeMapData.credit}
                        />
                    )}
                </div>
            </div>

            {summary && startDate && (
                <SummaryBar
                    summary={summary}
                    selectedMonth={startDate.slice(0, 7)}
                    handleFileUpload={importTransactions}
                />
            )}

            <div className="my-6">
                <MonthlyStatisticsCard
                    statistics={statistics}
                    isLoading={statsLoading}
                    error={statsError}
                    onMonthsChange={setStatisticsMonths}
                />
            </div>

            {transactions.length === 0 ? (
                <p className="text-gray-500">Geen transacties gevonden.</p>
            ) : (
                <>
                    <FilterBar
                        selectedCategoryNames={selectedCategoryNames}
                        clearCategory={(name) =>
                            setSelectedCategoryNames((prev) => prev.filter((n) => n !== name))
                        }
                        finalSelection={finalSelection}
                        clearDateSelection={clearSelection}
                    />
                    <TransactionTable
                        accountId={accountId!}
                        transactions={filteredTransactions}
                        refresh={refresh}
                    />
                </>
            )}
        </div>
    );
}
