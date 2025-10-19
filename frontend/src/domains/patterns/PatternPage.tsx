// src/domains/patterns/PatternPage.tsx

import {useCallback, useEffect, useState} from "react";
import PatternList from "./components/PatternList";
import PatternForm from "./components/PatternForm.tsx";
import PatternDiscovery from "./components/PatternDiscovery.tsx";
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
            transactionType: transaction.type,
        }
        : undefined;

    const [resetSignal, setResetSignal] = useState(0);
    const [editingPattern, setEditingPattern] = useState<PatternDTO | undefined>(undefined);

    const handleSuccess = (wasEdit: boolean = false, updatedPattern?: PatternDTO) => {
        setResetSignal(prev => prev + 1);
        // Alleen edit mode verlaten als het een nieuw patroon was (niet bij update)
        if (!wasEdit) {
            setEditingPattern(undefined);
        } else if (updatedPattern) {
            // Bij update: vervang editingPattern met de geüpdatete data
            setEditingPattern(updatedPattern);
        }
        refresh();
    };

    const refresh = useCallback(async () => {
        try {
            await getPatternsForAccount(accountId);
        } catch (error) {
            console.error('Error refreshing patterns:', error);
        }
    }, [accountId]);

    useEffect(() => {
        refresh();
    }, [refresh]);

    return (
        <div className="p-4 space-y-6">
            {/* AI Pattern Discovery Section */}
            <div>
                <h1 className="text-xl font-semibold mb-4">AI Patroonontdekking</h1>
                <PatternDiscovery onSuccess={handleSuccess} />
            </div>

            {/* Divider */}
            <div className="border-t border-gray-200" />

            {/* Manual Pattern Creation Section */}
            <div>
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
            </div>

            {/* Pattern List Section */}
            <div>
                <h2 className="text-xl font-semibold mb-4">Patronen beheren</h2>
                <PatternList
                    resetSignal={resetSignal}
                    onEdit={setEditingPattern}
                />
            </div>
        </div>
    );
}
