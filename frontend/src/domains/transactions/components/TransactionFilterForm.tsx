import { useState, useMemo } from "react";
import SimpleCategoryCombobox from "../../categories/components/SimpleCategoryCombobox";
import { createPattern } from "../../patterns/services/PatternService";
import { toast } from "react-hot-toast";
import type { Transaction } from "../models/Transaction";
import { ChevronDown, ChevronUp } from "lucide-react";

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
    const [patternMode, setPatternMode] = useState(false);
    const [patternCategory, setPatternCategory] = useState<number | null>(null);
    const [patternStrict, setPatternStrict] = useState(false);

    // Check if user is actively filtering
    const hasActiveFilters = useMemo(() => {
        return !!(filters.description || filters.notes || filters.tag ||
                  filters.minAmount || filters.maxAmount ||
                  filters.categoryId || filters.withoutCategory);
    }, [filters]);

    // Calculate pattern match statistics (only when in pattern mode)
    const patternStats = useMemo(() => {
        if (!patternMode || !patternCategory) return null;

        const conflicting = filteredTransactions.filter(t => t.category?.id && t.category.id !== patternCategory);
        const matching = filteredTransactions.filter(t => t.category?.id === patternCategory);
        const without = filteredTransactions.length - matching.length - conflicting.length;

        return { conflicting, matching, without };
    }, [patternMode, patternCategory, filteredTransactions]);

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
        if (!patternCategory) {
            toast.error("Selecteer een categorie");
            return;
        }

        try {
            const payload = {
                accountId,
                ...filters,
                categoryId: patternCategory,
                strict: patternStrict,
            };
            await createPattern(accountId, payload);
            toast.success("Patroon aangemaakt!");

            // Reset pattern mode
            setPatternMode(false);
            setPatternCategory(null);
            setPatternStrict(false);

            onRefresh();
        } catch (error) {
            console.error(error);
            toast.error("Fout bij aanmaken patroon");
        }
    };

    return (
        <div className="bg-white rounded-lg shadow mb-4">
            {/* Filter Section */}
            <div className="p-4 border-b">
                <div className="flex justify-between items-center mb-4">
                    <h2 className="text-lg font-semibold">Filters</h2>
                    <div className="flex gap-2 items-center">
                        {onOpenAiSuggestions && (
                            <button
                                onClick={onOpenAiSuggestions}
                                className="px-3 py-1.5 text-sm bg-purple-500 text-white rounded hover:bg-purple-600 flex items-center gap-1.5"
                            >
                                <span>✨</span>
                                AI Suggesties
                            </button>
                        )}
                        {hasActiveFilters && (
                            <button
                                onClick={clearAllFilters}
                                className="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50"
                            >
                                Wis filters
                            </button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-4 mb-3">
                    {/* Text filters */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Omschrijving</label>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={filters.description ?? ""}
                                onChange={(e) => updateFilter("description", e.target.value)}
                                className="flex-1 border border-gray-300 rounded px-3 py-1.5 text-sm"
                                placeholder="Zoek in omschrijving..."
                            />
                            <select
                                value={filters.matchTypeDescription ?? "LIKE"}
                                onChange={(e) => updateFilter("matchTypeDescription", e.target.value)}
                                className="w-24 border border-gray-300 rounded px-2 py-1.5 text-sm"
                            >
                                <option value="LIKE">LIKE</option>
                                <option value="EXACT">EXACT</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notities</label>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={filters.notes ?? ""}
                                onChange={(e) => updateFilter("notes", e.target.value)}
                                className="flex-1 border border-gray-300 rounded px-3 py-1.5 text-sm"
                                placeholder="Zoek in notities..."
                            />
                            <select
                                value={filters.matchTypeNotes ?? "LIKE"}
                                onChange={(e) => updateFilter("matchTypeNotes", e.target.value)}
                                className="w-24 border border-gray-300 rounded px-2 py-1.5 text-sm"
                            >
                                <option value="LIKE">LIKE</option>
                                <option value="EXACT">EXACT</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-4 gap-4 mb-3">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tag</label>
                        <input
                            type="text"
                            value={filters.tag ?? ""}
                            onChange={(e) => updateFilter("tag", e.target.value)}
                            className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                            placeholder="Tag..."
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Min bedrag (€)</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={filters.minAmount ?? ""}
                            onChange={(e) => {
                                const value = e.target.value === "" ? undefined : parseFloat(e.target.value);
                                updateFilter("minAmount", value);
                            }}
                            className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Max bedrag (€)</label>
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            value={filters.maxAmount ?? ""}
                            onChange={(e) => {
                                const value = e.target.value === "" ? undefined : parseFloat(e.target.value);
                                updateFilter("maxAmount", value);
                            }}
                            className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select
                            value={filters.transactionType ?? "both"}
                            onChange={(e) => updateFilter("transactionType", e.target.value)}
                            className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                        >
                            <option value="both">Beiden</option>
                            <option value="debit">Uitgaven</option>
                            <option value="credit">Inkomsten</option>
                        </select>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Startdatum</label>
                        <input
                            type="date"
                            value={filters.startDate ?? ""}
                            onChange={(e) => updateFilter("startDate", e.target.value)}
                            className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                        />
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Einddatum</label>
                        <input
                            type="date"
                            value={filters.endDate ?? ""}
                            onChange={(e) => updateFilter("endDate", e.target.value)}
                            className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm"
                        />
                    </div>

                    <div className="flex items-end gap-3">
                        <label className="inline-flex items-center text-sm cursor-pointer">
                            <input
                                type="checkbox"
                                checked={filterByPeriod}
                                onChange={(e) => onFilterByPeriodChange(e.target.checked)}
                                className="mr-2"
                            />
                            <span>Filter binnen periode</span>
                        </label>
                        <label className="inline-flex items-center text-sm cursor-pointer">
                            <input
                                type="checkbox"
                                checked={filters.withoutCategory ?? false}
                                onChange={(e) => updateFilter("withoutCategory", e.target.checked)}
                                className="mr-2"
                            />
                            <span>Zonder categorie</span>
                        </label>
                    </div>
                </div>
            </div>

            {/* Pattern Creation Section */}
            <div className="border-t">
                <button
                    onClick={() => setPatternMode(!patternMode)}
                    className="w-full px-4 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors"
                >
                    <span className="font-semibold text-gray-700">Maak patroon van filter</span>
                    {patternMode ? <ChevronUp className="w-5 h-5" /> : <ChevronDown className="w-5 h-5" />}
                </button>

                {patternMode && (
                    <div className="p-4 bg-gray-50">
                        <p className="text-sm text-gray-600 mb-4">
                            Maak een patroon dat automatisch toekomstige transacties categoriseert op basis van de huidige filters.
                        </p>

                        {/* Pattern Match Preview */}
                        {patternStats && filteredTransactions.length > 0 && (
                            <div className="border border-gray-200 rounded mb-4 text-sm bg-white">
                                <div className="p-3 border-b font-semibold bg-gray-50 text-gray-800">
                                    🎯 {filteredTransactions.length} transacties gevonden
                                </div>

                                {patternStats.conflicting.length > 0 && (
                                    <FeedbackBox type="error">
                                        ⚠️ {patternStats.conflicting.length} transacties hebben al een <b>andere categorie</b>.
                                    </FeedbackBox>
                                )}
                                {patternStats.matching.length > 0 && (
                                    <FeedbackBox type="success">
                                        ✅ {patternStats.matching.length} transacties hebben deze <b>categorie</b> al.
                                    </FeedbackBox>
                                )}
                                {patternStats.without > 0 && (
                                    <FeedbackBox type="new">
                                        🆕 {patternStats.without} nieuwe toewijzingen aan deze <b>categorie</b>.
                                    </FeedbackBox>
                                )}
                            </div>
                        )}

                        <div className="flex gap-4 items-end">
                            <div className="flex-1">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Categorie</label>
                                <SimpleCategoryCombobox
                                    categoryId={patternCategory}
                                    onChange={(c) => setPatternCategory(c?.id ?? null)}
                                    refreshCategories={() => {}}
                                    transactionType={filters.transactionType}
                                />
                            </div>

                            <label className="inline-flex items-center text-sm cursor-pointer group">
                                <input
                                    type="checkbox"
                                    checked={patternStrict}
                                    onChange={(e) => setPatternStrict(e.target.checked)}
                                    className="mr-2"
                                />
                                <span>Overschrijf bestaande</span>
                                <span className="ml-1 text-gray-400 cursor-help group-hover:underline"
                                      title="Als dit aanstaat, worden ook transacties met een bestaande categorie overschreven.">
                                    ⓘ
                                </span>
                            </label>

                            <button
                                onClick={handleCreatePattern}
                                disabled={!patternCategory}
                                className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 disabled:bg-gray-300 disabled:cursor-not-allowed"
                            >
                                Maak patroon
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
