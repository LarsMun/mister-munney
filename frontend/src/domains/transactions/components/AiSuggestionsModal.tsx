import { useState, useEffect } from "react";
import * as Dialog from "@radix-ui/react-dialog";
import { X, Check, AlertCircle, Sparkles } from "lucide-react";
import { getAiCategorySuggestions, bulkAssignCategories, type AiCategorySuggestion } from "../services/AiService";
import type { Transaction } from "../models/Transaction";
import { toast } from "react-hot-toast";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import { formatDate } from "../../../shared/utils/DateFormat";

interface Props {
    accountId: number;
    open: boolean;
    onClose: () => void;
    onSuccess: () => void;
    transactions: Transaction[];
    categories: Array<{ id: number; name: string; color: string }>;
}

interface SuggestionWithTransaction extends AiCategorySuggestion {
    transaction: Transaction;
    categoryName?: string;
    categoryColor?: string;
    selected: boolean;
}

export default function AiSuggestionsModal({ accountId, open, onClose, onSuccess, transactions, categories }: Props) {
    const [loading, setLoading] = useState(false);
    const [suggestions, setSuggestions] = useState<SuggestionWithTransaction[]>([]);
    const [applying, setApplying] = useState(false);

    useEffect(() => {
        if (open && transactions.length > 0) {
            loadSuggestions();
        }
    }, [open, transactions]);

    const loadSuggestions = async () => {
        setLoading(true);
        try {
            // Only send IDs of uncategorized transactions that are currently filtered
            const uncategorizedIds = transactions
                .filter(t => !t.category)
                .map(t => t.id);
            const response = await getAiCategorySuggestions(accountId, uncategorizedIds, 50);

            if (response.suggestions.length === 0) {
                toast.success(response.message || "Geen transacties gevonden om te categoriseren");
                onClose();
                return;
            }

            const enrichedSuggestions: SuggestionWithTransaction[] = response.suggestions
                .map((suggestion) => {
                    const transaction = transactions.find(t => t.id === suggestion.transactionId);
                    if (!transaction) return null;

                    const category = categories.find(c => c.id === suggestion.suggestedCategoryId);

                    return {
                        ...suggestion,
                        transaction,
                        categoryName: category?.name,
                        categoryColor: category?.color,
                        selected: suggestion.confidence >= 0.7 // Auto-select high confidence suggestions
                    };
                })
                .filter((s): s is SuggestionWithTransaction => s !== null);

            setSuggestions(enrichedSuggestions);
        } catch (error: any) {
            console.error('Failed to load AI suggestions', error);
            toast.error(error.response?.data?.error || "Fout bij ophalen AI suggesties");
            onClose();
        } finally {
            setLoading(false);
        }
    };

    const toggleSelection = (transactionId: number) => {
        setSuggestions(prev =>
            prev.map(s =>
                s.transactionId === transactionId
                    ? { ...s, selected: !s.selected }
                    : s
            )
        );
    };

    const selectAll = () => {
        setSuggestions(prev => prev.map(s => ({ ...s, selected: true })));
    };

    const deselectAll = () => {
        setSuggestions(prev => prev.map(s => ({ ...s, selected: false })));
    };

    const applySelected = async () => {
        const selectedSuggestions = suggestions.filter(s => s.selected && s.suggestedCategoryId !== null);

        if (selectedSuggestions.length === 0) {
            toast.error("Geen suggesties geselecteerd");
            return;
        }

        setApplying(true);
        try {
            const assignments = selectedSuggestions.map(s => ({
                transactionId: s.transactionId,
                categoryId: s.suggestedCategoryId!
            }));

            const result = await bulkAssignCategories(accountId, { assignments });

            if (result.success > 0) {
                toast.success(`${result.success} transacties gecategoriseerd!`);
                onSuccess();
                onClose();
            }

            if (result.failed > 0) {
                toast.error(`${result.failed} transacties gefaald`);
                result.errors.forEach(error => console.error(error));
            }
        } catch (error: any) {
            console.error('Failed to apply categories', error);
            toast.error("Fout bij toepassen categorieën");
        } finally {
            setApplying(false);
        }
    };

    const selectedCount = suggestions.filter(s => s.selected).length;
    const highConfidenceCount = suggestions.filter(s => s.confidence >= 0.7).length;

    return (
        <Dialog.Root open={open} onOpenChange={onClose}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/50 z-40" />
                <Dialog.Content className="fixed left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg shadow-xl p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto z-50">
                    <div className="flex items-center justify-between mb-4 border-b pb-3">
                        <div className="flex items-center gap-2">
                            <Sparkles className="w-6 h-6 text-purple-500" />
                            <Dialog.Title className="text-xl font-bold">
                                AI Categorie Suggesties
                            </Dialog.Title>
                        </div>
                        <button
                            onClick={onClose}
                            className="p-1 rounded hover:bg-gray-100 text-gray-500"
                            disabled={applying}
                        >
                            <X size={20} />
                        </button>
                    </div>

                    {loading ? (
                        <div className="flex flex-col items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-500 mb-4"></div>
                            <p className="text-gray-600">AI analyseert transacties...</p>
                        </div>
                    ) : (
                        <>
                            <div className="flex justify-between items-center mb-4 p-3 bg-purple-50 rounded-lg">
                                <div className="text-sm">
                                    <p className="font-semibold text-purple-900">
                                        {suggestions.length} suggesties gevonden
                                    </p>
                                    <p className="text-purple-700">
                                        {highConfidenceCount} met hoge zekerheid (&gt;70%)
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        onClick={selectAll}
                                        className="px-3 py-1 text-sm border border-purple-300 rounded hover:bg-purple-100"
                                        disabled={applying}
                                    >
                                        Selecteer alles
                                    </button>
                                    <button
                                        onClick={deselectAll}
                                        className="px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-100"
                                        disabled={applying}
                                    >
                                        Deselecteer alles
                                    </button>
                                </div>
                            </div>

                            <div className="space-y-2 mb-4">
                                {suggestions.map((suggestion) => (
                                    <SuggestionRow
                                        key={suggestion.transactionId}
                                        suggestion={suggestion}
                                        onToggle={() => toggleSelection(suggestion.transactionId)}
                                        disabled={applying}
                                    />
                                ))}
                            </div>

                            <div className="flex justify-between items-center pt-4 border-t">
                                <p className="text-sm text-gray-600">
                                    {selectedCount} van {suggestions.length} geselecteerd
                                </p>
                                <div className="flex gap-2">
                                    <button
                                        onClick={onClose}
                                        className="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50"
                                        disabled={applying}
                                    >
                                        Annuleren
                                    </button>
                                    <button
                                        onClick={applySelected}
                                        className="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                        disabled={applying || selectedCount === 0}
                                    >
                                        {applying ? "Toepassen..." : `Pas ${selectedCount} toe`}
                                    </button>
                                </div>
                            </div>
                        </>
                    )}
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}

function SuggestionRow({ suggestion, onToggle, disabled }: {
    suggestion: SuggestionWithTransaction;
    onToggle: () => void;
    disabled: boolean;
}) {
    const confidenceColor = suggestion.confidence >= 0.8 ? 'text-green-600' :
                           suggestion.confidence >= 0.6 ? 'text-yellow-600' :
                           'text-orange-600';

    const confidencePercentage = Math.round(suggestion.confidence * 100);

    return (
        <div
            className={`flex items-center gap-3 p-3 border rounded-lg cursor-pointer transition-colors ${
                suggestion.selected ? 'bg-purple-50 border-purple-300' : 'bg-white border-gray-200 hover:bg-gray-50'
            }`}
            onClick={onToggle}
        >
            <input
                type="checkbox"
                checked={suggestion.selected}
                onChange={(e) => {
                    e.stopPropagation();
                    onToggle();
                }}
                className="w-5 h-5 text-purple-500"
                disabled={disabled}
            />

            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                    <p className="font-medium text-gray-900 truncate">
                        {suggestion.transaction.description}
                    </p>
                    <span className={`text-xs font-semibold ${confidenceColor}`}>
                        {confidencePercentage}%
                    </span>
                </div>
                <div className="flex items-center gap-3 text-xs text-gray-600">
                    <span>{formatDate(suggestion.transaction.date)}</span>
                    <span className={suggestion.transaction.transactionType === 'debit' ? 'text-red-600' : 'text-green-600'}>
                        {suggestion.transaction.transactionType === 'debit' ? '-' : '+'}{formatMoney(suggestion.transaction.amount)}
                    </span>
                </div>
            </div>

            {suggestion.suggestedCategoryId ? (
                <div className="flex items-center gap-2">
                    <div
                        className="w-3 h-3 rounded-full"
                        style={{ backgroundColor: suggestion.categoryColor || '#999' }}
                    />
                    <span className="text-sm font-medium text-gray-700 whitespace-nowrap">
                        {suggestion.categoryName || 'Onbekend'}
                    </span>
                </div>
            ) : (
                <div className="flex items-center gap-2 text-gray-500">
                    <AlertCircle size={16} />
                    <span className="text-sm">Geen match</span>
                </div>
            )}

            <div className="text-xs text-gray-500 italic max-w-xs truncate">
                {suggestion.reasoning}
            </div>
        </div>
    );
}
