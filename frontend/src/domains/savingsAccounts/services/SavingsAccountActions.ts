// SavingsAccountActions.ts

import { createSavingsAccount } from "./SavingsAccountService";
import { SavingsAccount } from "../models/SavingsAccount";
import toast from "react-hot-toast";
import React from "react";
import {getRandomPrimaryHex} from "../utils/savingsAccountsUtils.ts";

export async function handleAddSavingsAccount(
    newData: Partial<SavingsAccount>,
    accountId: number,
    setSavingsAccounts: React.Dispatch<React.SetStateAction<SavingsAccount[]>>
): Promise<SavingsAccount> {
    const color = getRandomPrimaryHex();
    const newSA = await createSavingsAccount(accountId, {
        ...newData,
        color,
    });

    setSavingsAccounts(prev => [...prev, newSA]);
    toast.success("Spaarrekening succesvol aangemaakt");
    return newSA;
}

export async function handleAssignSavingsAccountToTransaction(
    savingsAccount: SavingsAccount | null,
    transactionId: number,
    accountId: number,
    refresh: () => void
) {
    try {
        await assignSavingsAccountToTransaction(accountId, transactionId, savingsAccount?.id ?? null);
        await refresh();
        toast.success(
            savingsAccount
                ? `Spaarrekening "${savingsAccount.name}" gekoppeld`
                : `Spaarrekening verwijderd`
        );
    } catch (error) {
        console.error("Fout bij koppelen van spaarrekening:", error);
        toast.error("Koppelen van spaarrekening mislukt");
    }
}