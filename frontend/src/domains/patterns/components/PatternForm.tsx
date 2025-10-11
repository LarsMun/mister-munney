// src/domains/patterns/components/PatternForm.tsx

import {useEffect, useState} from "react";
import type { PatternInput } from "../models/PatternInput";
import {createPattern, updatePattern} from "../services/PatternService";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import PatternFormElements from "./PatternFormElements";
import { toast } from "react-hot-toast";
import PatternMatchList from "./PatternMatchList.tsx";
import {PatternDTO} from "../models/PatternDTO.ts";

interface Props {
    prefill?: {
        description?: string;
        notes?: string;
        categoryId?: number | null;
        savingsAccountId?: number | null;
        transactionType?: 'debit' | 'credit';
    };
    editPattern?: PatternDTO;
    onSuccess: () => void;
}

export default function PatternForm({ prefill, editPattern, onSuccess }: Props) {
    const accountId = useRequiredAccount();

    const initialPattern: PatternInput = editPattern ? {
        // Als we editen, gebruik de waarden van editPattern
        accountId,
        patternType: "category",
        matchTypeDescription: editPattern.matchTypeDescription ?? "LIKE",
        matchTypeNotes: editPattern.matchTypeNotes ?? "LIKE",
        description: editPattern.description ?? "",
        categoryId: editPattern.category?.id ?? undefined,
        savingsAccountId: editPattern.savingsAccount?.id ?? undefined,
        notes: editPattern.notes ?? "",
        tag: editPattern.tag ?? "",
        transactionType: editPattern.transactionType ?? "debit",
        minAmount: editPattern.minAmount ?? undefined,
        maxAmount: editPattern.maxAmount ?? undefined,
        startDate: editPattern.startDate ?? undefined,
        endDate: editPattern.endDate ?? undefined,
        strict: editPattern.strict ?? false,
    } : {
        // Anders gebruik de normale initial values
        accountId,
        patternType: "category",
        matchTypeDescription: "LIKE",
        matchTypeNotes: "LIKE",
        description: prefill?.description ?? "",
        categoryId: prefill?.categoryId ?? undefined,
        savingsAccountId: prefill?.savingsAccountId ?? undefined,
        notes: "",
        tag: "",
        transactionType: prefill?.transactionType ?? "debit",
        minAmount: undefined,
        maxAmount: undefined,
        startDate: undefined,
        endDate: undefined,
        strict: false,
    };

    const [pattern, setPattern] = useState<PatternInput>(initialPattern);
    const [saving, setSaving] = useState(false);
    const [resetSignal, setResetSignal] = useState(0);

    useEffect(() => {
        if (editPattern) {
            setPattern({
                accountId,
                patternType: "category",
                matchTypeDescription: editPattern.matchTypeDescription ?? "LIKE",
                matchTypeNotes: editPattern.matchTypeNotes ?? "LIKE",
                description: editPattern.description ?? "",
                categoryId: editPattern.category?.id ?? undefined,
                savingsAccountId: editPattern.savingsAccount?.id ?? undefined,
                notes: editPattern.notes ?? "",
                tag: editPattern.tag ?? "",
                transactionType: editPattern.transactionType ?? "debit",
                minAmount: editPattern.minAmount ?? undefined,
                maxAmount: editPattern.maxAmount ?? undefined,
                startDate: editPattern.startDate ?? undefined,
                endDate: editPattern.endDate ?? undefined,
                strict: editPattern.strict ?? false,
            });
        }
    }, [editPattern, accountId]);

    const handleSave = async () => {
        setSaving(true);
        const toastId = toast.loading(editPattern ? "Patroon wordt bijgewerkt..." : "Patroon wordt opgeslagen...");

        try {
            const { patternType, ...payload } = pattern;

            if (editPattern) {
                // Update bestaand pattern
                await updatePattern(accountId, editPattern.id, payload);
                toast.success("Patroon bijgewerkt", { id: toastId });
            } else {
                // Nieuw pattern aanmaken
                await createPattern(accountId, payload);
                toast.success("Patroon opgeslagen", { id: toastId });
            }

            setPattern(initialPattern);
            setResetSignal(prev => prev + 1);
            onSuccess();
        } catch (e: any) {
            console.error("Fout bij opslaan:", e);
            const backendMessage = e?.response?.data?.error;
            const fallbackMessage = e?.message || "Onbekende fout";
            toast.error(`Opslaan mislukt: ${backendMessage ?? fallbackMessage}`, { id: toastId });
        } finally {
            setSaving(false);
        }
    };

    return (
        <form className="space-y-4 text-sm p-2 bg-gray-50 border border-gray-200 rounded">
            {editPattern && (
                <div className="text-xs text-blue-600 font-medium mb-2">
                    ✏️ Patroon aan het bewerken (ID: {editPattern.id})
                </div>
            )}

            <PatternFormElements
                pattern={pattern}
                updateField={(key, value) => setPattern({...pattern, [key]: value})}
            />

            <div className="flex justify-end gap-4 text-xs text-gray-600 pt-2">
                <button
                    type="button"
                    onClick={() => setPattern(initialPattern)}
                    className="text-blue-600 hover:underline"
                >
                    ↩ Reset
                </button>

                <button
                    type="button"
                    onClick={handleSave}
                    disabled={saving}
                    className="px-4 py-1.5 bg-blue-600 text-white rounded shadow hover:bg-blue-700 disabled:opacity-50"
                >
                    {saving ? "Bezig..." : editPattern ? "Bijwerken" : "Opslaan"}
                </button>
            </div>

            <PatternMatchList pattern={pattern} resetSignal={resetSignal} />
        </form>
    );
}