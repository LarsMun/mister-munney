// src/domains/patterns/components/PatternDiscovery.tsx

import { useState, useEffect } from "react";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import { discoverPatterns, acceptPatternSuggestion, rejectPatternSuggestion, PatternSuggestion } from "../services/PatternService";
import { toast } from "react-hot-toast";
import { Sparkles, Check, X, ChevronDown, ChevronUp, Edit2, Clock } from "lucide-react";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import CategoryCombobox from "../../categories/components/CategoryCombobox";
import { Category } from "../../categories/models/Category";
import { fetchCategories } from "../../categories/services/CategoryService";

const API_PREFIX = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';

interface Props {
    onSuccess?: (wasEdit?: boolean, updatedPattern?: any) => void;
}

export default function PatternDiscovery({ onSuccess }: Props) {
    const accountId = useRequiredAccount();
    const [loading, setLoading] = useState(false);
    const [discovering, setDiscovering] = useState(false);
    const [suggestions, setSuggestions] = useState<PatternSuggestion[]>([]);
    const [totalUncategorized, setTotalUncategorized] = useState<number>(0);
    const [expandedSuggestions, setExpandedSuggestions] = useState<Set<number>>(new Set());
    const [editingSuggestion, setEditingSuggestion] = useState<number | null>(null);
    const [categories, setCategories] = useState<Category[]>([]);
    const [editedData, setEditedData] = useState<{
        [key: number]: {
            categoryName: string;
            categoryId: number | null;
            descriptionPattern: string | null;
            notesPattern: string | null;
        }
    }>({});
    const [matchingTransactions, setMatchingTransactions] = useState<{
        [key: number]: {
            loading: boolean;
            data: any[] | null;
            total: number | null;
        }
    }>({});

    useEffect(() => {
        loadCategories();
    }, [accountId]);

    const loadCategories = async () => {
        try {
            const cats = await fetchCategories(accountId);
            setCategories(cats);
        } catch (error) {
            console.error("Failed to load categories", error);
        }
    };

    const handleDiscover = async () => {
        setDiscovering(true);
        try {
            const result = await discoverPatterns(accountId);
            setSuggestions(result.patterns);
            setTotalUncategorized(result.analyzedCount);

            if (result.patterns.length === 0) {
                toast.success("Geen nieuwe patronen gevonden");
            } else {
                toast.success(`${result.patterns.length} patronen ontdekt`);
            }
        } catch (error) {
            toast.error("Patroonontdekking mislukt");
            console.error(error);
        } finally {
            setDiscovering(false);
        }
    };

    const handleAccept = async (index: number, suggestion: PatternSuggestion) => {
        setLoading(true);
        try {
            const edited = editedData[index];

            // Use edited data if available, otherwise use original suggestion
            const descriptionPattern = edited?.descriptionPattern !== undefined ? edited.descriptionPattern : suggestion.descriptionPattern;
            const notesPattern = edited?.notesPattern !== undefined ? edited.notesPattern : suggestion.notesPattern;
            const categoryName = edited?.categoryName || suggestion.suggestedCategoryName;
            const categoryId = edited?.categoryId !== undefined ? edited.categoryId : suggestion.existingCategoryId;

            // Validation: at least one pattern must be provided
            if (!descriptionPattern && !notesPattern) {
                toast.error("Minimaal één pattern (description of notes) moet ingevuld zijn");
                setLoading(false);
                return;
            }

            await acceptPatternSuggestion(accountId, {
                descriptionPattern: descriptionPattern,
                notesPattern: notesPattern,
                categoryName: categoryName,
                categoryId: categoryId,
                categoryColor: undefined
            });

            const patternDisplay = [descriptionPattern, notesPattern].filter(Boolean).join(' + ');
            toast.success(`Patroon "${patternDisplay}" geaccepteerd`);

            // Remove accepted suggestion from list
            setSuggestions(prev => prev.filter((_, i) => i !== index));

            // Remove from edited data
            setEditedData(prev => {
                const newData = { ...prev };
                delete newData[index];
                return newData;
            });

            // Close editing mode
            setEditingSuggestion(null);

            if (onSuccess) {
                onSuccess();
            }
        } catch (error) {
            toast.error("Accepteren mislukt");
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const startEditing = (index: number, suggestion: PatternSuggestion) => {
        setEditingSuggestion(index);
        if (!editedData[index]) {
            setEditedData(prev => ({
                ...prev,
                [index]: {
                    categoryName: suggestion.suggestedCategoryName,
                    categoryId: suggestion.existingCategoryId,
                    descriptionPattern: suggestion.descriptionPattern,
                    notesPattern: suggestion.notesPattern
                }
            }));
        }
    };

    const stopEditing = () => {
        setEditingSuggestion(null);
    };

    const updateEditedCategory = (index: number, category: Category | null, suggestion: PatternSuggestion) => {
        setEditedData(prev => {
            const currentData = prev[index];
            return {
                ...prev,
                [index]: {
                    descriptionPattern: currentData?.descriptionPattern !== undefined ? currentData.descriptionPattern : suggestion.descriptionPattern,
                    notesPattern: currentData?.notesPattern !== undefined ? currentData.notesPattern : suggestion.notesPattern,
                    categoryName: category?.name || '',
                    categoryId: category?.id || null
                }
            };
        });
    };

    const updateEditedDescriptionPattern = (index: number, descriptionPattern: string, suggestion: PatternSuggestion) => {
        setEditedData(prev => {
            const currentData = prev[index];
            return {
                ...prev,
                [index]: {
                    descriptionPattern: descriptionPattern || null,
                    notesPattern: currentData?.notesPattern !== undefined ? currentData.notesPattern : suggestion.notesPattern,
                    categoryName: currentData?.categoryName || suggestion.suggestedCategoryName,
                    categoryId: currentData?.categoryId !== undefined ? currentData.categoryId : suggestion.existingCategoryId
                }
            };
        });
    };

    const updateEditedNotesPattern = (index: number, notesPattern: string, suggestion: PatternSuggestion) => {
        setEditedData(prev => {
            const currentData = prev[index];
            return {
                ...prev,
                [index]: {
                    descriptionPattern: currentData?.descriptionPattern !== undefined ? currentData.descriptionPattern : suggestion.descriptionPattern,
                    notesPattern: notesPattern || null,
                    categoryName: currentData?.categoryName || suggestion.suggestedCategoryName,
                    categoryId: currentData?.categoryId !== undefined ? currentData.categoryId : suggestion.existingCategoryId
                }
            };
        });
    };

    const handleReject = async (index: number, suggestion: PatternSuggestion) => {
        setLoading(true);
        try {
            await rejectPatternSuggestion(accountId, {
                descriptionPattern: suggestion.descriptionPattern,
                notesPattern: suggestion.notesPattern
            });

            const patternDisplay = [suggestion.descriptionPattern, suggestion.notesPattern].filter(Boolean).join(' + ');
            toast.success(`Patroon "${patternDisplay}" afgewezen`);

            // Remove rejected suggestion from list
            setSuggestions(prev => prev.filter((_, i) => i !== index));

            // Remove from edited data
            setEditedData(prev => {
                const newData = { ...prev };
                delete newData[index];
                return newData;
            });
        } catch (error) {
            toast.error("Afwijzen mislukt");
            console.error(error);
        } finally {
            setLoading(false);
        }
    };

    const loadMatchingTransactions = async (index: number, suggestion: PatternSuggestion) => {
        // If already loaded or loading, skip
        if (matchingTransactions[index]?.data || matchingTransactions[index]?.loading) {
            return;
        }

        // Set loading state
        setMatchingTransactions(prev => ({
            ...prev,
            [index]: { loading: true, data: null, total: null }
        }));

        try {
            const edited = editedData[index];
            const descriptionPattern = edited?.descriptionPattern !== undefined ? edited.descriptionPattern : suggestion.descriptionPattern;
            const notesPattern = edited?.notesPattern !== undefined ? edited.notesPattern : suggestion.notesPattern;

            const response = await fetch(`${API_PREFIX}/account/${accountId}/patterns/match`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    accountId: accountId,
                    description: descriptionPattern,
                    matchTypeDescription: descriptionPattern ? 'LIKE' : null,
                    notes: notesPattern,
                    matchTypeNotes: notesPattern ? 'LIKE' : null,
                })
            });

            if (!response.ok) {
                throw new Error('Failed to load matching transactions');
            }

            const result = await response.json();

            setMatchingTransactions(prev => ({
                ...prev,
                [index]: {
                    loading: false,
                    data: result.data,
                    total: result.total
                }
            }));
        } catch (error) {
            console.error('Failed to load matching transactions', error);
            toast.error('Kon overeenkomende transacties niet laden');
            setMatchingTransactions(prev => ({
                ...prev,
                [index]: { loading: false, data: null, total: null }
            }));
        }
    };

    const toggleExpanded = async (index: number, suggestion: PatternSuggestion) => {
        const isCurrentlyExpanded = expandedSuggestions.has(index);

        setExpandedSuggestions(prev => {
            const newSet = new Set(prev);
            if (newSet.has(index)) {
                newSet.delete(index);
            } else {
                newSet.add(index);
            }
            return newSet;
        });

        // Load matching transactions when expanding
        if (!isCurrentlyExpanded) {
            await loadMatchingTransactions(index, suggestion);
        }
    };

    return (
        <div className="space-y-4">
            {/* Discover Button */}
            <div className="flex items-center gap-4">
                <button
                    onClick={handleDiscover}
                    disabled={discovering}
                    className="flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-500 text-white rounded-lg hover:from-purple-600 hover:to-pink-600 disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-md hover:shadow-lg"
                >
                    <Sparkles size={18} className={discovering ? "animate-spin" : ""} />
                    {discovering ? "Patronen ontdekken..." : "AI Patronen Ontdekken"}
                </button>

                {totalUncategorized > 0 && (
                    <span className="text-sm text-gray-600">
                        Gebaseerd op {totalUncategorized} ongecategoriseerde transacties
                    </span>
                )}
            </div>

            {/* Suggestions List */}
            {suggestions.length > 0 && (
                <div className="space-y-3">
                    <h3 className="text-lg font-semibold text-gray-800">
                        Voorgestelde Patronen ({suggestions.length})
                    </h3>

                    {suggestions.map((suggestion, index) => {
                        const isEditing = editingSuggestion === index;
                        const edited = editedData[index];
                        const displayCategoryName = edited?.categoryName || suggestion.suggestedCategoryName;
                        const displayDescriptionPattern = edited?.descriptionPattern !== undefined ? edited.descriptionPattern : suggestion.descriptionPattern;
                        const displayNotesPattern = edited?.notesPattern !== undefined ? edited.notesPattern : suggestion.notesPattern;
                        const displayCategoryId = edited?.categoryId !== undefined ? edited.categoryId : suggestion.existingCategoryId;

                        // Build pattern display string
                        const patternParts = [];
                        if (displayDescriptionPattern) patternParts.push(`Desc: "${displayDescriptionPattern}"`);
                        if (displayNotesPattern) patternParts.push(`Notes: "${displayNotesPattern}"`);
                        const patternDisplay = patternParts.join(' + ');

                        return (
                            <div
                                key={index}
                                className="border rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow"
                            >
                                <div className="p-4">
                                    {/* Header */}
                                    <div className="flex items-start justify-between mb-2">
                                        <div className="flex-1">
                                            {isEditing ? (
                                                <div className="space-y-2 mb-2">
                                                    <div>
                                                        <label className="text-xs text-gray-600 mb-1 block">Description pattern:</label>
                                                        <input
                                                            type="text"
                                                            value={displayDescriptionPattern || ''}
                                                            onChange={(e) => updateEditedDescriptionPattern(index, e.target.value, suggestion)}
                                                            className="w-full border rounded px-2 py-1 text-sm"
                                                            placeholder="Optioneel"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="text-xs text-gray-600 mb-1 block">Notes pattern:</label>
                                                        <input
                                                            type="text"
                                                            value={displayNotesPattern || ''}
                                                            onChange={(e) => updateEditedNotesPattern(index, e.target.value, suggestion)}
                                                            className="w-full border rounded px-2 py-1 text-sm"
                                                            placeholder="Optioneel"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="text-xs text-gray-600 mb-1 block">Categorie:</label>
                                                        <CategoryCombobox
                                                            categoryId={displayCategoryId}
                                                            onChange={(cat) => updateEditedCategory(index, cat, suggestion)}
                                                            categories={categories}
                                                            setCategories={setCategories}
                                                        />
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-semibold text-gray-900 text-sm">
                                                        {patternDisplay}
                                                    </span>
                                                    <span className="text-sm text-gray-500">→</span>
                                                    <span className="px-2 py-0.5 rounded text-sm font-medium bg-blue-100 text-blue-800">
                                                        {displayCategoryName}
                                                    </span>
                                                    {suggestion.previouslyDiscovered && (
                                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800" title="Dit patroon was eerder ontdekt maar nog niet verwerkt">
                                                            <Clock size={12} />
                                                            Eerder ontdekt
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                            <div className="flex items-center gap-3 text-xs text-gray-600">
                                                <span>{suggestion.matchCount} transacties</span>
                                                <span>•</span>
                                                <span>
                                                    Zekerheid: {Math.round(suggestion.confidence * 100)}%
                                                </span>
                                                <span>•</span>
                                                <span className="italic">{suggestion.reasoning}</span>
                                            </div>
                                        </div>

                                        {/* Action Buttons */}
                                        <div className="flex gap-2 ml-4">
                                            {isEditing ? (
                                                <>
                                                    <button
                                                        onClick={() => handleAccept(index, suggestion)}
                                                        disabled={loading}
                                                        className="p-1.5 text-green-600 hover:bg-green-50 rounded transition"
                                                        title="Opslaan en accepteren"
                                                    >
                                                        <Check size={18} />
                                                    </button>
                                                    <button
                                                        onClick={stopEditing}
                                                        disabled={loading}
                                                        className="p-1.5 text-gray-600 hover:bg-gray-50 rounded transition"
                                                        title="Annuleren"
                                                    >
                                                        <X size={18} />
                                                    </button>
                                                </>
                                            ) : (
                                                <>
                                                    <button
                                                        onClick={() => handleAccept(index, suggestion)}
                                                        disabled={loading}
                                                        className="p-1.5 text-green-600 hover:bg-green-50 rounded transition"
                                                        title="Accepteren"
                                                    >
                                                        <Check size={18} />
                                                    </button>
                                                    <button
                                                        onClick={() => startEditing(index, suggestion)}
                                                        disabled={loading}
                                                        className="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition"
                                                        title="Bewerken"
                                                    >
                                                        <Edit2 size={18} />
                                                    </button>
                                                    <button
                                                        onClick={() => handleReject(index, suggestion)}
                                                        disabled={loading}
                                                        className="p-1.5 text-red-600 hover:bg-red-50 rounded transition"
                                                        title="Afwijzen"
                                                    >
                                                        <X size={18} />
                                                    </button>
                                                    <button
                                                        onClick={() => toggleExpanded(index, suggestion)}
                                                        className="p-1.5 text-gray-600 hover:bg-gray-50 rounded transition"
                                                        title="Details tonen/verbergen"
                                                    >
                                                        {expandedSuggestions.has(index) ? (
                                                            <ChevronUp size={18} />
                                                        ) : (
                                                            <ChevronDown size={18} />
                                                        )}
                                                    </button>
                                                </>
                                            )}
                                        </div>
                                    </div>

                                    {/* Matching Transactions (Expandable) */}
                                    {expandedSuggestions.has(index) && (
                                        <div className="mt-3 pt-3 border-t">
                                            <p className="text-xs font-medium text-gray-600 mb-2">
                                                {matchingTransactions[index]?.loading ? (
                                                    "Laden..."
                                                ) : matchingTransactions[index]?.total !== null ? (
                                                    `Overeenkomende transacties (${matchingTransactions[index].total}):`
                                                ) : (
                                                    "Overeenkomende transacties:"
                                                )}
                                            </p>

                                            {matchingTransactions[index]?.loading && (
                                                <div className="text-center py-4 text-gray-500 text-sm">
                                                    <div className="animate-pulse">Transacties laden...</div>
                                                </div>
                                            )}

                                            {matchingTransactions[index]?.data && matchingTransactions[index].data.length > 0 && (
                                                <div className="overflow-x-auto">
                                                    <table className="min-w-full text-xs">
                                                        <thead>
                                                            <tr className="border-b">
                                                                <th className="text-left py-2 px-2 font-medium text-gray-600">Datum</th>
                                                                <th className="text-left py-2 px-2 font-medium text-gray-600">Beschrijving</th>
                                                                <th className="text-left py-2 px-2 font-medium text-gray-600">Notities</th>
                                                                <th className="text-right py-2 px-2 font-medium text-gray-600">Bedrag</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            {matchingTransactions[index].data.map((tx: any) => (
                                                                <tr key={tx.id} className="border-b hover:bg-gray-50">
                                                                    <td className="py-2 px-2 text-gray-700 whitespace-nowrap">
                                                                        {tx.date}
                                                                    </td>
                                                                    <td className="py-2 px-2 text-gray-700">
                                                                        {tx.description}
                                                                    </td>
                                                                    <td className="py-2 px-2 text-gray-500 max-w-xs">
                                                                        <div
                                                                            className="truncate"
                                                                            title={tx.notes || ''}
                                                                        >
                                                                            {tx.notes || '-'}
                                                                        </div>
                                                                    </td>
                                                                    <td className="py-2 px-2 text-right font-medium whitespace-nowrap">
                                                                        {formatMoney(tx.amount)}
                                                                    </td>
                                                                </tr>
                                                            ))}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            )}

                                            {matchingTransactions[index]?.data && matchingTransactions[index].data.length === 0 && (
                                                <div className="text-center py-4 text-gray-500 text-sm">
                                                    Geen overeenkomende transacties gevonden
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
