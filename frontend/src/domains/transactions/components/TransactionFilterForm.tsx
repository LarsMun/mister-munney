import { useState, useMemo } from "react";
import SimpleCategoryCombobox from "../../categories/components/SimpleCategoryCombobox";
import SimpleSavingsAccountCombobox from "../../savingsAccounts/components/SimpleSavingsAccountCombobox";
import { createPattern } from "../../patterns/services/PatternService";
import { toast } from "react-hot-toast";
import type { Transaction } from "../models/Transaction";

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

interface Props {
    accountId: number;
    filters: FilterState;
    onFilterChange: (filters: FilterState) => void;
    onRefresh: () => void;
    filterByPeriod: boolean;
    onFilterByPeriodChange: (filter: boolean) => void;
    filteredTransactions: Transaction[];
    onOpenAiSuggestions?: () => void;
}

function FeedbackBox({ type, children }: { type: "error" | "success" | "new"; children: React.ReactNode }) {
    const base = "p-2 text-xs border-b rounded";
    const styles = {
        error: "text-red-700 bg-red-50 border-red-200",
        success: "text-green-700 bg-green-50 border-green-200",
        new: "text-blue-700 bg-blue-50 border-blue-200",
    };
    return <div className={`${base} ${styles[type]}`}>{children}</div>;
}

export default function TransactionFilterForm({
    accountId,
    filters,
    onFilterChange,
    onRefresh,
    filterByPeriod,
    onFilterByPeriodChange,
    filteredTransactions,
    onOpenAiSuggestions
}: Props) {

    // Calculate pattern match statistics
    const conflictingCategory = useMemo(() => {
        if (!filters.categoryId || filters.strict) return [];
        return filteredTransactions.filter(t => t.category?.id && t.category.id !== filters.categoryId);
    }, [filteredTransactions, filters.categoryId, filters.strict]);

    const conflictingSavings = useMemo(() => {
        if (!filters.savingsAccountId || filters.strict) return [];
        return filteredTransactions.filter(t => t.savingsAccount?.id && t.savingsAccount.id !== filters.savingsAccountId);
    }, [filteredTransactions, filters.savingsAccountId, filters.strict]);

    const matchingCategory = useMemo(() => {
        if (!filters.categoryId) return [];
        return filteredTransactions.filter(t => t.category?.id === filters.categoryId);
    }, [filteredTransactions, filters.categoryId]);

    const matchingSavings = useMemo(() => {
        if (!filters.savingsAccountId) return [];
        return filteredTransactions.filter(t => t.savingsAccount?.id === filters.savingsAccountId);
    }, [filteredTransactions, filters.savingsAccountId]);

    const withoutCategory = filters.categoryId != null
        ? filteredTransactions.length - matchingCategory.length - conflictingCategory.length
        : 0;

    const withoutSavingsAccount = filters.savingsAccountId != null
        ? filteredTransactions.length - matchingSavings.length - conflictingSavings.length
        : 0;

    const updateFilter = (key: keyof FilterState, value: any) => {
        const newFilters = { ...filters, [key]: value };
        onFilterChange(newFilters);
    };

    const clearAllFilters = () => {
        const emptyFilters: FilterState = {
            matchTypeDescription: "LIKE",
            matchTypeNotes: "LIKE",
            transactionType: "both",
            strict: false,
        };
        onFilterChange(emptyFilters);
        onFilterByPeriodChange(false);
    };

    const handleCreatePattern = async () => {
        if (!filters.categoryId && !filters.savingsAccountId) {
            toast.error("Selecteer een categorie of spaarrekening");
            return;
        }

        try {
            const payload = {
                accountId,
                ...filters,
            };
            await createPattern(accountId, payload);
            toast.success("Patroon aangemaakt!");

            // Clear filter form but keep toggles
            const clearedFilters: FilterState = {
                matchTypeDescription: "LIKE",
                matchTypeNotes: "LIKE",
                transactionType: "both",
                strict: false,
                withoutCategory: filters.withoutCategory,
            };
            onFilterChange(clearedFilters);

            onRefresh();
        } catch (error) {
            console.error(error);
            toast.error("Fout bij aanmaken patroon");
        }
    };

    return (
        <div className="bg-white rounded-lg shadow p-4 mb-4">
            <div className="flex justify-between items-center mb-4">
                <h2 className="text-lg font-semibold">Filters</h2>
                <div className="flex gap-2">
                    <label className="inline-flex items-center text-sm">
                        <input
                            type="checkbox"
                            checked={filters.withoutCategory ?? false}
                            onChange={(e) => updateFilter("withoutCategory", e.target.checked)}
                            className="mr-2"
                        />
                        Alleen zonder categorie
                    </label>
                    <label className="inline-flex items-center text-sm">
                        <input
                            type="checkbox"
                            checked={filterByPeriod}
                            onChange={(e) => onFilterByPeriodChange(e.target.checked)}
                            className="mr-2"
                        />
                        Filter binnen geselecteerde periode
                    </label>
                    {onOpenAiSuggestions && (
                        <button
                            onClick={onOpenAiSuggestions}
                            className="px-3 py-1 text-sm bg-purple-500 text-white rounded hover:bg-purple-600 flex items-center gap-1"
                        >
                            <span>✨</span>
                            AI Suggesties
                        </button>
                    )}
                    <button
                        onClick={clearAllFilters}
                        className="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50"
                    >
                        Wis filters
                    </button>
                </div>
            </div>

            {/* Rij 1: Omschrijving + matchtype + notities + matchtype */}
            <div className="flex gap-4 items-start mb-3">
                <div className="flex-1">
                    <label className="block text-xs font-medium text-gray-600">Omschrijving</label>
                    <input
                        type="text"
                        value={filters.description ?? ""}
                        onChange={(e) => updateFilter("description", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                        placeholder="Zoek in omschrijving..."
                    />
                </div>

                <div className="w-28">
                    <label className="block text-xs font-medium text-gray-600">Type</label>
                    <select
                        value={filters.matchTypeDescription ?? "LIKE"}
                        onChange={(e) => updateFilter("matchTypeDescription", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                    >
                        <option value="LIKE">LIKE</option>
                        <option value="EXACT">EXACT</option>
                    </select>
                </div>

                <div className="flex-1">
                    <label className="block text-xs font-medium text-gray-600">Notities</label>
                    <textarea
                        value={filters.notes ?? ""}
                        onChange={(e) => updateFilter("notes", e.target.value)}
                        className="w-full border rounded p-1 resize-none text-xs"
                        placeholder="Zoek in notities..."
                        rows={2}
                    />
                </div>

                <div className="w-28">
                    <label className="block text-xs font-medium text-gray-600">Type</label>
                    <select
                        value={filters.matchTypeNotes ?? "LIKE"}
                        onChange={(e) => updateFilter("matchTypeNotes", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                    >
                        <option value="LIKE">LIKE</option>
                        <option value="EXACT">EXACT</option>
                    </select>
                </div>
            </div>

            {/* Rij 2: Tag + Datums + Bedragen + Type */}
            <div className="flex gap-4 items-end mb-3">
                <div className="w-40">
                    <label className="block text-xs font-medium text-gray-600">Tag</label>
                    <input
                        type="text"
                        value={filters.tag ?? ""}
                        onChange={(e) => updateFilter("tag", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                        placeholder="Tag..."
                    />
                </div>

                <div className="w-40">
                    <label className="block text-xs font-medium text-gray-600">Startdatum</label>
                    <input
                        type="date"
                        value={filters.startDate ?? ""}
                        onChange={(e) => updateFilter("startDate", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-40">
                    <label className="block text-xs font-medium text-gray-600">Einddatum</label>
                    <input
                        type="date"
                        value={filters.endDate ?? ""}
                        onChange={(e) => updateFilter("endDate", e.target.value)}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-28">
                    <label className="block text-xs font-medium text-gray-600">Min bedrag</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={filters.minAmount ?? ""}
                        onChange={(e) => {
                            const value = e.target.value === "" ? undefined : parseFloat(e.target.value);
                            updateFilter("minAmount", value);
                        }}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-28">
                    <label className="block text-xs font-medium text-gray-600">Max bedrag</label>
                    <input
                        type="number"
                        min="0"
                        step="0.01"
                        value={filters.maxAmount ?? ""}
                        onChange={(e) => {
                            const value = e.target.value === "" ? undefined : parseFloat(e.target.value);
                            updateFilter("maxAmount", value);
                        }}
                        className="w-full border rounded p-1 h-8"
                    />
                </div>

                <div className="w-32">
                    <label className="block text-xs font-medium text-gray-600">Transactie type</label>
                    <select
                        value={filters.transactionType ?? "both"}
                        onChange={(e) => updateFilter("transactionType", e.target.value)}
                        className="w-full border rounded p-1 h-8 text-xs"
                    >
                        <option value="both">Beiden</option>
                        <option value="debit">Af</option>
                        <option value="credit">Bij</option>
                    </select>
                </div>
            </div>

            {/* Pattern Creation Section */}
            <div className="border-t pt-4 mt-4">
                <h3 className="text-sm font-semibold mb-3">Maak patroon van filter</h3>

                {/* Pattern Match Preview */}
                {filteredTransactions.length > 0 && (
                    <div className="border border-gray-200 rounded mb-3 text-sm">
                        <div className="p-2 border-b font-semibold bg-gray-50 text-gray-800">
                            🎯 {filteredTransactions.length} transacties gevonden
                        </div>

                        {filters.categoryId != null && (
                            <>
                                {conflictingCategory.length > 0 && (
                                    <FeedbackBox type="error">
                                        ⚠️ {conflictingCategory.length} transacties hebben al een <b>andere categorie</b>.
                                    </FeedbackBox>
                                )}
                                {matchingCategory.length > 0 && (
                                    <FeedbackBox type="success">
                                        ✅ {matchingCategory.length} transacties hebben deze <b>categorie</b> al.
                                    </FeedbackBox>
                                )}
                                {withoutCategory > 0 && (
                                    <FeedbackBox type="new">
                                        🆕 {withoutCategory} nieuwe toewijzingen aan deze <b>categorie</b>.
                                    </FeedbackBox>
                                )}
                            </>
                        )}

                        {filters.savingsAccountId != null && (
                            <>
                                {conflictingSavings.length > 0 && (
                                    <FeedbackBox type="error">
                                        ⚠️ {conflictingSavings.length} transacties hebben al een <b>andere spaarrekening</b>.
                                    </FeedbackBox>
                                )}
                                {matchingSavings.length > 0 && (
                                    <FeedbackBox type="success">
                                        ✅ {matchingSavings.length} transacties hebben deze <b>spaarrekening</b> al.
                                    </FeedbackBox>
                                )}
                                {withoutSavingsAccount > 0 && (
                                    <FeedbackBox type="new">
                                        🆕 {withoutSavingsAccount} nieuwe toewijzingen aan deze <b>spaarrekening</b>.
                                    </FeedbackBox>
                                )}
                            </>
                        )}
                    </div>
                )}

                <div className="flex gap-4 items-end">
                    <div className="w-64">
                        <label className="block text-xs font-medium text-gray-600">Categorie</label>
                        <SimpleCategoryCombobox
                            categoryId={filters.categoryId ?? null}
                            onChange={(c) => updateFilter("categoryId", c?.id ?? null)}
                            refreshCategories={() => {}}
                            transactionType={filters.transactionType}
                        />
                    </div>

                    <div className="w-64">
                        <label className="block text-xs font-medium text-gray-600">Spaarrekening</label>
                        <SimpleSavingsAccountCombobox
                            savingsAccountId={filters.savingsAccountId ?? null}
                            onChange={(sa) => updateFilter("savingsAccountId", sa?.id ?? null)}
                        />
                    </div>

                    <div className="flex items-center h-8">
                        <label className="inline-flex items-center text-xs text-gray-700 group">
                            <input
                                type="checkbox"
                                checked={filters.strict ?? false}
                                onChange={(e) => updateFilter("strict", e.target.checked)}
                                className="mr-2"
                            />
                            Overschrijf bestaande
                            <span className="ml-1 text-gray-400 cursor-help group-hover:underline"
                                  title="Als dit aanstaat, worden ook transacties met een bestaande categorie of spaarrekening overschreven.">
                                ⓘ
                            </span>
                        </label>
                    </div>

                    <button
                        onClick={handleCreatePattern}
                        className="px-4 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 h-8"
                    >
                        Maak patroon
                    </button>
                </div>
            </div>
        </div>
    );
}
