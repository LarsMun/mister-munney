import { useState, useEffect } from "react";
import { useTransactions } from './hooks/useTransactions';
import TransactionTable from './components/TransactionTable';
import { Toaster } from 'react-hot-toast';
import { useAccount } from "../../app/context/AccountContext.tsx";
import SummaryBar from "./components/SummaryBar.tsx";
import PeriodPicker from "./components/PeriodPicker.tsx";
import TransactionFilterForm from "./components/TransactionFilterForm.tsx";
import { getAllTransactions } from "./services/TransactionsService";
import { matchesPattern } from "../patterns/utils/matchesPattern";
import type { Transaction } from "./models/Transaction";
import { toast } from "react-hot-toast";

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
}

export default function TransactionPage() {
    const { accountId } = useAccount();
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

    const [filters, setFilters] = useState<FilterState>({});
    const [ignorePeriod, setIgnorePeriod] = useState(false);
    const [allTransactions, setAllTransactions] = useState<Transaction[]>([]);

    // Fetch all transactions when ignorePeriod is enabled
    useEffect(() => {
        if (ignorePeriod && accountId) {
            getAllTransactions(accountId)
                .then(setAllTransactions)
                .catch(() => toast.error("Fout bij ophalen alle transacties"));
        }
    }, [ignorePeriod, accountId]);

    // Apply filters to transactions
    const transactionsToFilter = ignorePeriod ? allTransactions : transactions;
    const filteredTransactions = transactionsToFilter.filter(t => {
        // If no filters are set, show all
        const hasFilters = Object.values(filters).some(v => v !== undefined && v !== "" && v !== null);
        if (!hasFilters) return true;

        // Use matchesPattern utility
        return matchesPattern(t, filters as any);
    });

    const handleIgnorePeriodChange = (ignore: boolean) => {
        setIgnorePeriod(ignore);
        if (!ignore) {
            setAllTransactions([]);
        }
    };

    // Combined refresh function that handles both period-based and all transactions
    const handleRefresh = () => {
        refresh(); // Always refresh period-based transactions
        if (ignorePeriod && accountId) {
            // Also refresh all transactions if ignore period is active
            getAllTransactions(accountId)
                .then(setAllTransactions)
                .catch(() => toast.error("Fout bij ophalen alle transacties"));
        }
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
                onFilterChange={setFilters}
                onRefresh={handleRefresh}
                ignorePeriod={ignorePeriod}
                onIgnorePeriodChange={handleIgnorePeriodChange}
                filteredTransactions={filteredTransactions}
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
                />
            )}
        </div>
    );
}
