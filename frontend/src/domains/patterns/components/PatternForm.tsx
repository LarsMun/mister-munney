// src/domains/patterns/components/PatternForm.tsx

import React, { useState } from "react";
import type { PatternInput } from "../models/PatternInput";
import { createPattern } from "../services/PatternService";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import PatternFormElements from "./PatternFormElements";
import { toast } from "react-hot-toast";
import PatternMatchList from "./PatternMatchList.tsx";

interface Props {
    prefill?: {
        description?: string;
        notes?: string;
        categoryId?: number | null;
        savingsAccountId?: number | null;

    };
    onSuccess: () => void;
}

export default function PatternForm({ prefill, onSuccess }: Props) {
    const accountId = useRequiredAccount();

    const initialPattern: PatternInput = {
        accountId,
        patternType: "category",
        matchTypeDescription: "LIKE",
        matchTypeNotes: "LIKE",
        description: prefill?.description ?? "",
        categoryId: prefill?.categoryId ?? undefined,
        savingsAccountId: prefill?.savingsAccountId ?? undefined,
        notes: "",
        tag: "",
        transactionType: undefined,
        minAmount: undefined,
        maxAmount: undefined,
        startDate: undefined,
        endDate: undefined,
        strict: false,
    };

    const [pattern, setPattern] = useState<PatternInput>(initialPattern);
    const [saving, setSaving] = useState(false);
    const [resetSignal, setResetSignal] = useState(0);

    const handleSave = async () => {
        setSaving(true);
        const toastId = toast.loading("Patroon wordt opgeslagen...");

        try {
            const { patternType, ...payload } = pattern;
            await createPattern(accountId, payload);
            toast.success("Patroon opgeslagen", { id: toastId });
            setPattern(initialPattern);
            setResetSignal(prev => prev + 1); // 🔁 Triggert reset
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
                    {saving ? "Bezig..." : "Opslaan"}
                </button>
            </div>

            <PatternMatchList pattern={pattern} resetSignal={resetSignal} />

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
                    {saving ? "Bezig..." : "Opslaan"}
                </button>
            </div>
        </form>
    );
}