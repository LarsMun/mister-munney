import { useEffect, useMemo, useState } from "react";
import type { PatternInput } from "../models/PatternInput";
import type { Transaction } from "../../transactions/models/Transaction";
import { fetchPatternMatches } from "../services/PatternService";
import { toast } from "react-hot-toast";
import { matchesPattern } from "../utils/matchesPattern";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import TransactionDrawer from "../../transactions/components/TransactionDrawer";

interface Props {
    pattern: PatternInput;
    resetSignal?: number;
}

// Simple debounce implementation to avoid lodash dependency issues
function debounce<T extends (...args: any[]) => any>(
    func: T,
    wait: number
): T & { cancel: () => void } {
    let timeout: NodeJS.Timeout | null = null;
    
    const debounced = function(this: any, ...args: Parameters<T>) {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    } as T & { cancel: () => void };
    
    debounced.cancel = () => {
        if (timeout) clearTimeout(timeout);
    };
    
    return debounced;
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

export default function PatternMatchList({ pattern, resetSignal }: Props) {
    const accountId = useRequiredAccount();
    const [matchesFromBackend, setMatchesFromBackend] = useState<Transaction[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [selectedTransaction, setSelectedTransaction] = useState<Transaction | null>(null);

    const relevantMatches = useMemo(
        () => matchesFromBackend.filter(t => matchesPattern(t, pattern)),
        [matchesFromBackend, pattern]
    );

    const conflictingCategory = !pattern.strict
        ? relevantMatches.filter(t => t.category?.id && t.category.id !== pattern.categoryId)
        : [];

    const conflictingSavings = !pattern.strict
        ? relevantMatches.filter(t => t.savingsAccount?.id && t.savingsAccount.id !== pattern.savingsAccountId)
        : [];

    const matchingCategory = useMemo(() =>
            relevantMatches.filter(t => pattern.categoryId != null && t.category?.id === pattern.categoryId),
        [relevantMatches, pattern.categoryId]
    );

    const matchingSavings = useMemo(() =>
            relevantMatches.filter(t => pattern.savingsAccountId != null && t.savingsAccount?.id === pattern.savingsAccountId),
        [relevantMatches, pattern.savingsAccountId]
    );

    const withoutCategory = pattern.categoryId != null
        ? relevantMatches.length - matchingCategory.length - conflictingCategory.length
        : 0;

    const withoutSavingsAccount = pattern.savingsAccountId != null
        ? relevantMatches.length - matchingSavings.length - conflictingSavings.length
        : 0;

    const handleFetch = async () => {
        setError(null);
        try {
            const { data } = await fetchPatternMatches(accountId, pattern);
            setMatchesFromBackend(data);
        } catch (e) {
            console.error("Fout bij ophalen matches:", e);
            toast.error("Fout bij ophalen transacties");
            setError("Fout bij ophalen van transacties.");
        }
    };

    useEffect(() => {
        // Check voor minimaal 2 karakters in description of notes
        const hasMinDescription = (pattern.description?.trim()?.length ?? 0) >= 2;
        const hasMinNotes = (pattern.notes?.trim()?.length ?? 0) >= 2;
        
        // Check voor andere velden
        const hasTag = !!pattern.tag?.trim();
        const hasAmountFilter = pattern.minAmount !== undefined || pattern.maxAmount !== undefined;
        const hasDateFilter = !!pattern.startDate || !!pattern.endDate;
        
        // Er moet minimaal één zinvol filter zijn:
        // - Minimaal 2 karakters in description of notes, OF
        // - Tag, bedrag-filter of datum-filter ingevuld
        // (transactionType alleen is te breed)
        const hasRelevantFields = hasMinDescription || hasMinNotes || hasTag || hasAmountFilter || hasDateFilter;

        if (!hasRelevantFields) {
            // Clear de lijst als er geen relevante velden zijn
            setMatchesFromBackend([]);
            return;
        }

        const debounced = debounce(handleFetch, 600);
        debounced();
        return () => debounced.cancel();
    }, [pattern]);

    useEffect(() => {
        setMatchesFromBackend([]);
    }, [resetSignal]);

    return (
        <div className="border border-gray-200 rounded mt-2 text-sm">
            <div className="p-2 border-b font-semibold bg-gray-50 text-gray-800">
                🎯 {relevantMatches.length} transacties gevonden
            </div>

            {pattern.categoryId != null && (
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

            {pattern.savingsAccountId != null && (
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

            {error && <div className="p-2 text-xs text-red-600">{error}</div>}
            {(pattern.categoryId != null || pattern.savingsAccountId != null) && relevantMatches.length > 0 && (
                <div className="h-5" />
            )}
            {relevantMatches.map((m) => {
                const isMismatch = pattern.categoryId != null && m.category?.id && m.category.id !== pattern.categoryId;

                return (
                    <div
                        key={m.id}
                        onClick={() => setSelectedTransaction(m)}
                        className={`flex justify-between items-center px-3 py-1 border-t cursor-pointer hover:bg-yellow-100 transition ${
                            isMismatch ? "bg-red-50" : "bg-green-50"
                        }`}
                    >
                        <div className="flex items-center gap-4 w-[34rem]">
                            <span className="w-[5.5rem] text-xs font-mono text-gray-600 shrink-0">{m.date}</span>
                            <span className="truncate text-xs text-gray-900">{m.description}</span>
                        </div>
                        <div className="text-right ml-4 w-32 font-mono text-xs">{formatMoney(m.amount)}</div>
                        <div className="ml-4 text-xs w-40 text-right">
                            {m.category ? (
                                <span className={`inline-block px-2 py-0.5 rounded ${
                                    isMismatch ? "bg-red-100 text-red-700 font-semibold" : "bg-green-100 text-gray-800"
                                }`}>{m.category.name}</span>
                            ) : (
                                <span className="text-gray-400 italic">Geen categorie</span>
                            )}
                        </div>
                    </div>
                );
            })}

            <TransactionDrawer
                transaction={selectedTransaction}
                onClose={() => setSelectedTransaction(null)}
            />
        </div>
    );
}
