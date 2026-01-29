// frontend/src/domains/forecast/ForecastPage.tsx

import { useForecast } from './hooks/useForecast';
import { StatusCard } from './components/StatusCard';
import { ForecastItemCard } from './components/ForecastItemCard';
import { AvailableItemsList } from './components/AvailableItemsList';
import { useAccount } from '../../app/context/AccountContext';
import { formatMoney } from '../../shared/utils/MoneyFormat';
import { formatMonthDisplay, getCurrentMonth } from './services/ForecastService';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import type { ForecastItem } from './models/Forecast';

export default function ForecastPage() {
    const { accountId } = useAccount();
    const {
        month,
        forecast,
        availableItems,
        isLoading,
        error,
        updateItem,
        removeItem,
        goToPreviousMonth,
        goToNextMonth,
        goToCurrentMonth,
        refresh,
    } = useForecast();

    if (!accountId) {
        return (
            <div className="text-center py-12">
                <p className="text-gray-600">Geen account geselecteerd</p>
            </div>
        );
    }

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
                <p>Fout bij het laden van de forecast: {error}</p>
                <button
                    onClick={refresh}
                    className="mt-2 bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700"
                >
                    Opnieuw proberen
                </button>
            </div>
        );
    }

    const currentMonth = getCurrentMonth();
    const isCurrentMonth = month === currentMonth;
    const canGoToNextMonth = month < currentMonth;

    return (
        <div className="min-h-screen bg-gray-50 p-4 md:p-6">
            <div className="max-w-4xl mx-auto space-y-6">
                {/* Header with Month Navigation */}
                <div className="flex items-center justify-between">
                    <h1 className="text-xl md:text-2xl font-bold text-gray-800">Cashflow Forecast</h1>
                    <div className="flex items-center gap-2">
                        <button
                            onClick={goToPreviousMonth}
                            className="p-2 text-gray-600 hover:bg-white hover:shadow-sm rounded-lg transition-all"
                            title="Vorige maand"
                        >
                            <ChevronLeft className="w-5 h-5" />
                        </button>

                        <button
                            onClick={goToCurrentMonth}
                            className={`px-3 md:px-4 py-2 rounded-lg font-medium transition-colors text-sm md:text-base ${
                                isCurrentMonth
                                    ? 'bg-blue-600 text-white shadow-md'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 shadow-sm'
                            }`}
                        >
                            {formatMonthDisplay(month)}
                        </button>

                        <button
                            onClick={goToNextMonth}
                            disabled={!canGoToNextMonth}
                            className={`p-2 rounded-lg transition-all ${
                                canGoToNextMonth
                                    ? 'text-gray-600 hover:bg-white hover:shadow-sm'
                                    : 'text-gray-300 cursor-not-allowed'
                            }`}
                            title="Volgende maand"
                        >
                            <ChevronRight className="w-5 h-5" />
                        </button>
                    </div>
                </div>

                {/* Main Status Card */}
                {forecast && (
                    <StatusCard
                        currentBalance={forecast.currentBalance}
                        expectedEndBalance={forecast.projectedBalance}
                        totalActualIncome={forecast.totalActualIncome}
                        totalAdjustedIncome={forecast.totalExpectedIncome}
                        totalActualExpenses={forecast.totalActualExpenses}
                        totalAdjustedExpenses={forecast.totalExpectedExpenses}
                    />
                )}

                {/* Income Section */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-700 flex items-center gap-2">
                            <span className="w-3 h-3 bg-emerald-500 rounded-full"></span>
                            Inkomsten
                        </h3>
                        <span className="text-sm text-gray-500">
                            {formatMoney(forecast?.totalActualIncome || 0)} /{' '}
                            {formatMoney(forecast?.totalExpectedIncome || 0)}
                        </span>
                    </div>

                    <div className="space-y-2">
                        {forecast?.incomeItems && forecast.incomeItems.length > 0 ? (
                            forecast.incomeItems.map((item: ForecastItem) => (
                                <ForecastItemCard
                                    key={item.id}
                                    item={item}
                                    type="income"
                                    onUpdate={updateItem}
                                    onRemove={removeItem}
                                />
                            ))
                        ) : (
                            <div className="bg-white border-2 border-dashed border-gray-200 rounded-lg p-8 text-center">
                                <p className="text-gray-500">Geen inkomsten toegevoegd</p>
                                <p className="text-sm text-gray-400 mt-1">
                                    Sleep budgetten of categorieën hierheen
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Expenses Section */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h3 className="font-semibold text-gray-700 flex items-center gap-2">
                            <span className="w-3 h-3 bg-blue-500 rounded-full"></span>
                            Uitgaven
                        </h3>
                        <span className="text-sm text-gray-500">
                            {formatMoney(forecast?.totalActualExpenses || 0)} /{' '}
                            {formatMoney(forecast?.totalExpectedExpenses || 0)}
                        </span>
                    </div>

                    <div className="space-y-2">
                        {forecast?.expenseItems && forecast.expenseItems.length > 0 ? (
                            forecast.expenseItems.map((item: ForecastItem) => (
                                <ForecastItemCard
                                    key={item.id}
                                    item={item}
                                    type="expense"
                                    onUpdate={updateItem}
                                    onRemove={removeItem}
                                />
                            ))
                        ) : (
                            <div className="bg-white border-2 border-dashed border-gray-200 rounded-lg p-8 text-center">
                                <p className="text-gray-500">Geen uitgaven toegevoegd</p>
                                <p className="text-sm text-gray-400 mt-1">
                                    Sleep budgetten of categorieën hierheen
                                </p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Available Items (collapsed at bottom on mobile, sidebar on desktop) */}
                <div className="lg:hidden">
                    <AvailableItemsList
                        budgets={availableItems.budgets}
                        categories={availableItems.categories}
                        onRefresh={refresh}
                    />
                </div>

                {/* Tip */}
                <div className="bg-blue-50 border border-blue-100 rounded-lg p-4 text-sm text-blue-800">
                    <strong>Tip:</strong> Klik op een "Nog €..." bedrag om je verwachting aan te passen. Zo maak je je
                    forecast nauwkeuriger op basis van wat je deze maand weet.
                </div>
            </div>

            {/* Sidebar for available items on desktop */}
            <div className="hidden lg:block fixed right-6 top-24 w-80">
                <AvailableItemsList
                    budgets={availableItems.budgets}
                    categories={availableItems.categories}
                    onRefresh={refresh}
                />
            </div>
        </div>
    );
}
