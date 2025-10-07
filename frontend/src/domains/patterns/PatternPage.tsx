// src/domains/patterns/PatternPage.tsx

import {useCallback, useEffect, useState} from "react";
import PatternList from "./components/PatternList";
import PatternForm from "./components/PatternForm.tsx";
import {useRequiredAccount} from "../../app/context/AccountContext.tsx";
import {PatternDTO} from "./models/PatternDTO.ts";
import {getPatternsForAccount} from "./services/PatternService.ts";
import {useLocation} from "react-router-dom";
import {Transaction} from "../transactions/models/Transaction.ts";

export default function PatternPage() {
    const accountId = useRequiredAccount();
    const location = useLocation();
    const transaction = (location.state as { transaction?: Transaction })?.transaction;
    const prefill = transaction
        ? {
            description: transaction.description,
            notes: transaction.notes,
            categoryId: transaction.category?.id ?? null,
            savingsAccountId: transaction.savingsAccount?.id ?? null,
        }
        : undefined;

    const [patterns, setPatterns] = useState<PatternDTO[]>([]);
    const [loading, setLoading] = useState(true);
    const [resetSignal, setResetSignal] = useState(0);
    const [editingPattern, setEditingPattern] = useState<PatternDTO | undefined>(undefined); // 🆕

    const handleSuccess = () => {
        setResetSignal(prev => prev + 1);
        setEditingPattern(undefined); // 🆕 Clear edit mode
        refresh();
    };

    const refresh = useCallback(async () => {
        setLoading(true);
        try {
            const result = await getPatternsForAccount(accountId);
            setPatterns(result);
        } finally {
            setLoading(false);
        }
    }, [accountId]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    return (
        <div className="p-4 space-y-4">
            <h1 className="text-xl font-semibold">
                {editingPattern ? "Patroon bewerken" : "Patroon toevoegen"}
            </h1>
            <PatternForm
                prefill={prefill}
                editPattern={editingPattern}
                onSuccess={handleSuccess}
            />
            {editingPattern && (
                <button
                    onClick={() => setEditingPattern(undefined)}
                    className="text-sm text-gray-600 hover:text-gray-800 underline"
                >
                    ← Annuleren en nieuw patroon maken
                </button>
            )}
            <h2 className="text-xl font-semibold">Patronen beheren</h2>
            <PatternList
                resetSignal={resetSignal}
                onEdit={setEditingPattern} // 🆕 Pass edit handler
            />
        </div>
    );
}