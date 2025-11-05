import { useState, useEffect } from "react";
import { useSearchParams } from "react-router-dom";
import { useTransactions } from './hooks/useTransactions';
import TransactionTable from './components/TransactionTable';
import { Toaster } from 'react-hot-toast';
import { useAccount } from "../../app/context/AccountContext.tsx";
import SummaryBar from "./components/SummaryBar.tsx";
import PeriodPicker from "./components/PeriodPicker.tsx";
import TransactionFilterForm from "./components/TransactionFilterForm.tsx";
import AiSuggestionsModal from "./components/AiSuggestionsModal.tsx";
import { getAllTransactions } from "./services/TransactionsService";
import { matchesPattern } from "../patterns/utils/matchesPattern";
import type { Transaction } from "./models/Transaction";
import { toast } from "react-hot-toast";
import { useCategories } from "../categories/hooks/useCategories";

interface FilterState {
    description?: string;
    matchTypeDescription?: "LIKE" | "EXACT";
    notes?: string;
    matchTypeNotes?: "LIKE" | "EXACT";
    tag?: string;
    minAmount?: number;
    maxAmount?: number;
    startDate?: string;
    endDate?: string;
    transactionType?: "debit" | "credit" | "both";
    categoryId?: number;
    savingsAccountId?: number;
    strict?: boolean;
    withoutCategory?: boolean;
}

export default function TransactionPage() {
    const { accountId } = useAccount();
    const [searchParams, setSearchParams] = useSearchParams();
    const {
        months,
        startDate,
        setStartDate,
        setEndDate,
        summary,
        transactions,
        refresh,
        importTransactions,
    } = useTransactions();

    const [filters, setFilters] = useState<FilterState>({
        matchTypeDescription: "LIKE",
        matchTypeNotes: "LIKE",
        transactionType: "both",
        strict: false,
    });
    const [filterByPeriod, setFilterByPeriod] = useState(false);
    const [allTransactions, setAllTransactions] = useState<Transaction[]>([]);
    const [aiModalOpen, setAiModalOpen] = useState(false);
    const { categories } = useCategories(accountId!);

    // Apply URL parameters on mount
    useEffect(() => {
        const categoryId = searchParams.get('categoryId');
        const startDateParam = searchParams.get('startDate');
        const endDateParam = searchParams.get('endDate');

        if (categoryId || startDateParam || endDateParam) {
            const newFilters: FilterState = { ...filters };

            if (categoryId) {
                newFilters.categoryId = parseInt(categoryId);
            }

            if (startDateParam) {
                newFilters.startDate = startDateParam;
                setStartDate(startDateParam);
                setFilterByPeriod(true);
            }

            if (endDateParam) {
                newFilters.endDate = endDateParam;
                setEndDate(endDateParam);
                setFilterByPeriod(true);
            }

            setFilters(newFilters);

            // Clear URL parameters after applying
            setSearchParams({});
        }
    }, [searchParams, setSearchParams]);

    // Fetch all transactions on mount (default behavior)
    useEffect(() => {
        if (accountId) {
            getAllTransactions(accountId)
                .then(setAllTransactions)
                .catch(() => toast.error("Fout bij ophalen alle transacties"));
        }
    }, [accountId]);

    // Check if any filters are applied (including categoryId for display filtering)
    const hasFilters = Object.entries(filters).some(([key, value]) => {
        // Don't count savingsAccountId, strict as filters for transaction display
        if (key === 'savingsAccountId' || key === 'strict') return false;
        // Don't count default values (matchType selectors and transactionType "both")
        if (key === 'matchTypeDescription' || key === 'matchTypeNotes' || key === 'transactionType') return false;
        // Count categoryId, withoutCategory, and other actual filter values
        return value !== undefined && value !== "" && value !== null;
    });

    // Apply filters to transactions
    // Logic: No filters = show period transactions
    //        Has filters + filterByPeriod unchecked = show all transactions
    //        Has filters + filterByPeriod checked = show period transactions
    const transactionsToFilter = (!hasFilters || filterByPeriod) ? transactions : allTransactions;

    const filteredTransactions = transactionsToFilter.filter(t => {
        // Exclude ONLY split child transactions - parent transactions should be visible
        // even if they have splits (they show adjusted amounts in budgets)
        if (t.parentTransactionId !== null && t.parentTransactionId !== undefined) {
            return false;
        }

        // Apply "without category" filter first
        if (filters.withoutCategory && t.category) {
            return false;
        }

        // Apply categoryId filter for display (not pattern matching)
        if (filters.categoryId !== undefined && t.category?.id !== filters.categoryId) {
            return false;
        }

        // If no filters are set, show all from the selected source
        if (!hasFilters) return true;

        // Use matchesPattern utility for other filters
        return matchesPattern(t, filters as any);
    });

    const handleFilterByPeriodChange = (filter: boolean) => {
        setFilterByPeriod(filter);
    };

    // Combined refresh function that handles both period-based and all transactions
    const handleRefresh = () => {
        refresh(); // Always refresh period-based transactions
        if (accountId) {
            // Also refresh all transactions
            getAllTransactions(accountId)
                .then(setAllTransactions)
                .catch(() => toast.error("Fout bij ophalen alle transacties"));
        }
    };

    // Handlers for clicking description/notes in transaction drawer
    const handleFilterByDescription = (description: string) => {
        const newFilters = { ...filters, description };
        setFilters(newFilters);
    };

    const handleFilterByNotes = (notes: string) => {
        const newFilters = { ...filters, notes };
        setFilters(newFilters);
    };

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

            <TransactionFilterForm
                accountId={accountId!}
                filters={filters}
                onFilterChange={setFilters}
                onRefresh={handleRefresh}
                filterByPeriod={filterByPeriod}
                onFilterByPeriodChange={handleFilterByPeriodChange}
                filteredTransactions={filteredTransactions}
                onOpenAiSuggestions={() => setAiModalOpen(true)}
            />

            <AiSuggestionsModal
                accountId={accountId!}
                open={aiModalOpen}
                onClose={() => setAiModalOpen(false)}
                onSuccess={handleRefresh}
                transactions={allTransactions}
                categories={categories.map(c => ({ id: c.id, name: c.name, color: c.color }))}
            />

            {summary && startDate && (
                <SummaryBar
                    summary={summary}
                    selectedMonth={startDate.slice(0, 7)}
                    handleFileUpload={importTransactions}
                />
            )}

            {filteredTransactions.length === 0 ? (
                <p className="text-gray-500">Geen transacties gevonden.</p>
            ) : (
                <TransactionTable
                    accountId={accountId!}
                    transactions={filteredTransactions}
                    refresh={handleRefresh}
                    onFilterByDescription={handleFilterByDescription}
                    onFilterByNotes={handleFilterByNotes}
                />
            )}
        </div>
    );
}
