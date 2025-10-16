// src/domains/patterns/components/PatternDiscovery.tsx

import { useState } from "react";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import { discoverPatterns, acceptPatternSuggestion, PatternSuggestion } from "../services/PatternService";
import { toast } from "react-hot-toast";
import { Sparkles, Check, X, ChevronDown, ChevronUp } from "lucide-react";
import { formatMoney } from "../../../shared/utils/MoneyFormat";

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

    const handleAccept = async (suggestion: PatternSuggestion, categoryColor?: string) => {
        setLoading(true);
        try {
            await acceptPatternSuggestion(accountId, {
                patternString: suggestion.patternString,
                categoryName: suggestion.suggestedCategoryName,
                categoryId: suggestion.existingCategoryId,
                categoryColor: categoryColor
            });

            toast.success(`Patroon "${suggestion.patternString}" geaccepteerd`);

            // Remove accepted suggestion from list
            setSuggestions(prev => prev.filter(s => s.patternString !== suggestion.patternString));

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

                    {suggestions.map((suggestion, index) => (
                        <div
                            key={index}
                            className="border rounded-lg bg-white shadow-sm hover:shadow-md transition-shadow"
                        >
                            <div className="p-4">
                                {/* Header */}
                                <div className="flex items-start justify-between mb-2">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-1">
                                            <span className="font-semibold text-gray-900">
                                                {suggestion.patternString}
                                            </span>
                                            <span className="text-sm text-gray-500">→</span>
                                            <span className="px-2 py-0.5 rounded text-sm font-medium bg-blue-100 text-blue-800">
                                                {suggestion.suggestedCategoryName}
                                            </span>
                                        </div>
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
                                        <button
                                            onClick={() => handleAccept(suggestion)}
                                            disabled={loading}
                                            className="p-1.5 text-green-600 hover:bg-green-50 rounded transition"
                                            title="Accepteren"
                                        >
                                            <Check size={18} />
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
                    ))}
                </div>
            )}
        </div>
    );
}
