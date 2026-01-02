// frontend/src/domains/forecast/ForecastPage.tsx

import { useForecast } from './hooks/useForecast';
import { ForecastSection } from './components/ForecastSection';
import { AvailableItemsList } from './components/AvailableItemsList';
import { useAccount } from '../../app/context/AccountContext';
import { formatMoney } from '../../shared/utils/MoneyFormat';
import { formatMonthDisplay, getCurrentMonth } from './services/ForecastService';
import type { ForecastItem, PositionUpdate } from './models/Forecast';

export default function ForecastPage() {
    const { accountId } = useAccount();
    const {
        month,
        forecast,
        availableItems,
        isLoading,
        error,
        addItem,
        updateItem,
        removeItem,
        updatePositions,
        resetItemToMedian,
        resetTypeToMedian,
        goToPreviousMonth,
        goToNextMonth,
        goToCurrentMonth,
        refresh
    } = useForecast();

    const handleReorderIncomeItems = async (items: ForecastItem[]) => {
        const positions: PositionUpdate[] = items.map((item, index) => ({
            id: item.id,
            position: index,
            type: 'INCOME' as const
        }));
        await updatePositions(positions);
    };

    const handleReorderExpenseItems = async (items: ForecastItem[]) => {
        const positions: PositionUpdate[] = items.map((item, index) => ({
            id: item.id,
            position: index,
            type: 'EXPENSE' as const
        }));
        await updatePositions(positions);
    };

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
    // Disable next button if we're at current month or in the future
    const canGoToNextMonth = month < currentMonth;

    return (
        <div className="space-y-4 md:space-y-6">
            {/* Header with Month Navigation */}
            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 className="text-xl md:text-2xl font-bold text-gray-900">Cashflow Forecast</h1>
                    <p className="text-sm md:text-base text-gray-600 mt-1">
                        Beheer je verwachte inkomsten en uitgaven
                    </p>
                </div>

                <div className="flex items-center space-x-2">
                    <button
                        onClick={goToPreviousMonth}
                        className="p-2 text-gray-600 hover:bg-gray-100 rounded-lg"
                        title="Vorige maand"
                    >
                        ←
                    </button>

                    <button
                        onClick={goToCurrentMonth}
                        className={`px-3 md:px-4 py-2 rounded-lg font-medium transition-colors text-sm md:text-base ${
                            isCurrentMonth
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        {formatMonthDisplay(month)}
                    </button>

                    {canGoToNextMonth && (
                        <button
                            onClick={goToNextMonth}
                            className="p-2 text-gray-600 hover:bg-gray-100 rounded-lg"
                            title="Volgende maand"
                        >
                            →
                        </button>
                    )}
                </div>
            </div>

            {/* Summary Cards */}
            {forecast && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 md:gap-4">
                    {/* Current Balance */}
                    <div className="bg-white rounded-lg shadow-md p-3 md:p-4">
                        <div className="text-xs md:text-sm text-gray-500 mb-1">Huidig Saldo</div>
                        <div className={`text-lg md:text-2xl font-bold ${
                            forecast.currentBalance >= 0 ? 'text-gray-900' : 'text-red-600'
                        }`}>
                            {formatMoney(forecast.currentBalance)}
                        </div>
                    </div>

                    {/* Expected Result */}
                    <div className="bg-white rounded-lg shadow-md p-3 md:p-4">
                        <div className="text-xs md:text-sm text-gray-500 mb-1">Verwacht Resultaat</div>
                        <div className={`text-lg md:text-2xl font-bold ${
                            forecast.expectedResult >= 0 ? 'text-green-600' : 'text-red-600'
                        }`}>
                            {forecast.expectedResult >= 0 ? '+' : ''}{formatMoney(forecast.expectedResult)}
                        </div>
                        <div className="text-xs text-gray-500 mt-1 hidden sm:block">
                            {formatMoney(forecast.totalExpectedIncome)} - {formatMoney(forecast.totalExpectedExpenses)}
                        </div>
                    </div>

                    {/* Actual Result */}
                    <div className="bg-white rounded-lg shadow-md p-3 md:p-4">
                        <div className="text-xs md:text-sm text-gray-500 mb-1">Actueel Resultaat</div>
                        <div className={`text-lg md:text-2xl font-bold ${
                            forecast.actualResult >= 0 ? 'text-green-600' : 'text-red-600'
                        }`}>
                            {forecast.actualResult >= 0 ? '+' : ''}{formatMoney(forecast.actualResult)}
                        </div>
                        <div className="text-xs text-gray-500 mt-1 hidden sm:block">
                            {formatMoney(forecast.totalActualIncome)} - {formatMoney(forecast.totalActualExpenses)}
                        </div>
                    </div>

                    {/* Projected Balance */}
                    <div className="bg-white rounded-lg shadow-md p-3 md:p-4">
                        <div className="text-xs md:text-sm text-gray-500 mb-1">Verwacht Eindsaldo</div>
                        <div className={`text-lg md:text-2xl font-bold ${
                            forecast.projectedBalance >= 0 ? 'text-blue-600' : 'text-red-600'
                        }`}>
                            {formatMoney(forecast.projectedBalance)}
                        </div>
                        <div className="text-xs text-gray-500 mt-1 hidden sm:block">
                            na verwachte transacties
                        </div>
                    </div>
                </div>
            )}

            {/* Main Content */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6">
                {/* Available Items - moved to bottom on mobile */}
                <div className="lg:col-span-1 order-2 lg:order-1">
                    <div className="lg:sticky lg:top-4">
                        <AvailableItemsList
                            budgets={availableItems.budgets}
                            categories={availableItems.categories}
                            onRefresh={refresh}
                        />
                    </div>
                </div>

                {/* Forecast Sections - shown first on mobile */}
                <div className="lg:col-span-2 space-y-4 md:space-y-6 order-1 lg:order-2">
                    {/* Income Section */}
                    <ForecastSection
                        title="Inkomsten"
                        type="INCOME"
                        items={forecast?.incomeItems || []}
                        totalExpected={forecast?.totalExpectedIncome || 0}
                        totalActual={forecast?.totalActualIncome || 0}
                        onAddItem={addItem}
                        onUpdateItem={updateItem}
                        onRemoveItem={removeItem}
                        onResetItemToMedian={resetItemToMedian}
                        onResetAllToMedian={() => resetTypeToMedian('INCOME')}
                        onReorderItems={handleReorderIncomeItems}
                    />

                    {/* Expense Section */}
                    <ForecastSection
                        title="Uitgaven"
                        type="EXPENSE"
                        items={forecast?.expenseItems || []}
                        totalExpected={forecast?.totalExpectedExpenses || 0}
                        totalActual={forecast?.totalActualExpenses || 0}
                        onAddItem={addItem}
                        onUpdateItem={updateItem}
                        onRemoveItem={removeItem}
                        onResetItemToMedian={resetItemToMedian}
                        onResetAllToMedian={() => resetTypeToMedian('EXPENSE')}
                        onReorderItems={handleReorderExpenseItems}
                    />

                    {/* Help text */}
                    <div className="text-center text-sm text-gray-500 p-4 bg-gray-50 rounded-lg">
                        <p>
                            <strong>Tip:</strong> Klik op een verwacht bedrag om het aan te passen.
                            Sleep items om de volgorde te wijzigen.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
