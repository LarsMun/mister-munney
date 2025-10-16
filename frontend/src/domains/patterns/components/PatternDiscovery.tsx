// src/domains/patterns/components/PatternDiscovery.tsx

import { useState, useEffect } from "react";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import { discoverPatterns, acceptPatternSuggestion, PatternSuggestion } from "../services/PatternService";
import { toast } from "react-hot-toast";
import { Sparkles, Check, X, ChevronDown, ChevronUp, Edit2 } from "lucide-react";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import CategoryCombobox from "../../categories/components/CategoryCombobox";
import { Category } from "../../categories/models/Category";
import { fetchCategories } from "../../categories/services/CategoryService";

interface Props {
    onSuccess?: () => void;
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
            patternString: string;
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
            setTotalUncategorized(result.totalUncategorized);

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
            const data = edited || {
                categoryName: suggestion.suggestedCategoryName,
                categoryId: suggestion.existingCategoryId,
                patternString: suggestion.patternString
            };

            await acceptPatternSuggestion(accountId, {
                patternString: data.patternString,
                categoryName: data.categoryName,
                categoryId: data.categoryId,
                categoryColor: undefined
            });

            toast.success(`Patroon "${data.patternString}" geaccepteerd`);

            // Remove accepted suggestion from list
            setSuggestions(prev => prev.filter((_, i) => i !== index));

            // Remove from edited data
            setEditedData(prev => {
                const newData = { ...prev };
                delete newData[index];
                return newData;
            });

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
                    patternString: suggestion.patternString
                }
            }));
        }
    };

    const stopEditing = () => {
        setEditingSuggestion(null);
    };

    const updateEditedCategory = (index: number, category: Category | null) => {
        setEditedData(prev => ({
            ...prev,
            [index]: {
                ...prev[index],
                categoryName: category?.name || prev[index]?.categoryName || '',
                categoryId: category?.id || null
            }
        }));
    };

    const updateEditedPattern = (index: number, patternString: string) => {
        setEditedData(prev => ({
            ...prev,
            [index]: {
                ...prev[index],
                patternString,
                categoryName: prev[index]?.categoryName || '',
                categoryId: prev[index]?.categoryId || null
            }
        }));
    };

    const handleReject = (suggestion: PatternSuggestion) => {
        setSuggestions(prev => prev.filter(s => s.patternString !== suggestion.patternString));
        toast.success("Patroon afgewezen");
    };

    const toggleExpanded = (index: number) => {
        setExpandedSuggestions(prev => {
            const newSet = new Set(prev);
            if (newSet.has(index)) {
                newSet.delete(index);
            } else {
                newSet.add(index);
            }
            return newSet;
        });
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
                        {totalUncategorized} ongecategoriseerde transacties
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
                        const displayPatternString = edited?.patternString || suggestion.patternString;
                        const displayCategoryId = edited?.categoryId !== undefined ? edited.categoryId : suggestion.existingCategoryId;

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
                                                        <label className="text-xs text-gray-600 mb-1 block">Pattern string:</label>
                                                        <input
                                                            type="text"
                                                            value={displayPatternString}
                                                            onChange={(e) => updateEditedPattern(index, e.target.value)}
                                                            className="w-full border rounded px-2 py-1 text-sm"
                                                        />
                                                    </div>
                                                    <div>
                                                        <label className="text-xs text-gray-600 mb-1 block">Categorie:</label>
                                                        <CategoryCombobox
                                                            categoryId={displayCategoryId}
                                                            onChange={(cat) => updateEditedCategory(index, cat)}
                                                            categories={categories}
                                                            setCategories={setCategories}
                                                        />
                                                    </div>
                                                </div>
                                            ) : (
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-semibold text-gray-900">
                                                        {displayPatternString}
                                                    </span>
                                                    <span className="text-sm text-gray-500">→</span>
                                                    <span className="px-2 py-0.5 rounded text-sm font-medium bg-blue-100 text-blue-800">
                                                        {displayCategoryName}
                                                    </span>
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
                                                        onClick={() => handleReject(suggestion)}
                                                        disabled={loading}
                                                        className="p-1.5 text-red-600 hover:bg-red-50 rounded transition"
                                                        title="Afwijzen"
                                                    >
                                                        <X size={18} />
                                                    </button>
                                                    <button
                                                        onClick={() => toggleExpanded(index)}
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

                                    {/* Example Transactions (Expandable) */}
                                    {expandedSuggestions.has(index) && suggestion.exampleTransactions.length > 0 && (
                                        <div className="mt-3 pt-3 border-t">
                                            <p className="text-xs font-medium text-gray-600 mb-2">
                                                Voorbeeldtransacties:
                                            </p>
                                            <div className="space-y-1">
                                                {suggestion.exampleTransactions.map((tx) => (
                                                    <div
                                                        key={tx.id}
                                                        className="text-xs text-gray-700 flex justify-between items-center py-1 px-2 bg-gray-50 rounded"
                                                    >
                                                        <span className="truncate flex-1">{tx.description}</span>
                                                        <div className="flex items-center gap-2 ml-2">
                                                            <span className="text-gray-500">{tx.date}</span>
                                                            <span className="font-medium">{formatMoney(tx.amount)}</span>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
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
