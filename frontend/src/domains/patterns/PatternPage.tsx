// src/domains/patterns/PatternPage.tsx

import React, {useCallback, useEffect, useState} from "react";
import PatternList from "./components/PatternList";
import PatternForm from "./components/PatternForm.tsx";
import {useRequiredAccount} from "../../app/context/AccountContext.tsx";
import {PatternDTO} from "./models/PatternDTO.ts";
import {getPatternsForAccount} from "./services/PatternService.ts";
import {PatternInput} from "./models/PatternInput.ts";
import {PatternPrefill} from "./models/PatternPrfefill.ts";
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

    const handleSuccess = () => {
        setResetSignal(prev => prev + 1);
        refresh(); // eventueel ook: alleen refresh patternlijst
    };

    const refresh = useCallback(async () => {
        setLoading(true); // <-- hier toevoegen
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
            <h1 className="text-xl font-semibold">Patroon toevoegen</h1>
            <PatternForm prefill={prefill} onSuccess={handleSuccess} />
            <h2 className="text-xl font-semibold">Patronen beheren</h2>
            <PatternList resetSignal={resetSignal} />
        </div>
    );
}