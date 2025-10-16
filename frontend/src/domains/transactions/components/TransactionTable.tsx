// src/domains/transactions/components/TransactionTable.tsx

import React, { useState } from "react";
import { formatMoney } from "../../../shared/utils/MoneyFormat.tsx";
import { formatDate } from "../../../shared/utils/DateFormat.tsx";
import { useCategories } from "../../categories/hooks/useCategories";
import CategoryCombobox from "../../categories/components/CategoryCombobox.tsx";
import type { Transaction } from "../models/Transaction";
import TransactionDrawer from "./TransactionDrawer.tsx";
import SavingsAccountCombobox from "../../savingsAccounts/components/SavingsAccountCombobox.tsx";
import { useSavingsAccounts } from "../../savingsAccounts/hooks/useSavingsAccounts";

type Props = {
    accountId: number;
    transactions: Transaction[];
    refresh: () => void;
    onFilterByDescription?: (description: string) => void;
    onFilterByNotes?: (notes: string) => void;
};

export default function TransactionTable({ accountId, transactions, refresh, onFilterByDescription, onFilterByNotes }: Props) {

    const [sortBy, setSortBy] = useState<keyof Transaction>("date");
    const [sortDirection, setSortDirection] = useState<"asc" | "desc">("desc");
    const [selectedTx, setSelectedTx] = useState<Transaction | undefined>(undefined);

    const handleSort = (field: keyof Transaction) => {
        if (sortBy === field) {
            setSortDirection(prev => (prev === "asc" ? "desc" : "asc"));
        } else {
            setSortBy(field);
            setSortDirection("asc");
        }
    };

    const { savingsAccounts, addSavingsAccount } = useSavingsAccounts(accountId);

    const sortedTransactions = [...transactions].sort((a, b) => {
        let aValue = a[sortBy];
        let bValue = b[sortBy];

        // Speciale velden
        if (sortBy === "amount") {
            aValue = Number(a.amount);
            bValue = Number(b.amount);
        }
        if (sortBy === "date") {
            aValue = a.date;
            bValue = b.date;
        }
        if (sortBy === "category") {
            aValue = a.category?.name || "";
            bValue = b.category?.name || "";
        }

        if (aValue === undefined || bValue === undefined) {
            return 0;
        }

        if (typeof aValue === "number" && typeof bValue === "number") {
            return sortDirection === "asc" ? aValue - bValue : bValue - aValue;
        }

        if (typeof aValue === "string" && typeof bValue === "string") {
            return sortDirection === "asc"
                ? aValue.localeCompare(bValue)
                : bValue.localeCompare(aValue);
        }

        return 0;
    });

    const getSortIcon = (field: keyof Transaction) => {
        if (sortBy !== field) return null;
        return (
            <span className="ml-1">
            {sortDirection === "asc" ? "▲" : "▼"}
        </span>
        );
    };

    const { categories, setCategories } = useCategories(accountId);

    return (
        <div className="overflow-x-auto rounded-xl border border-gray-200 shadow">
            <table className="min-w-full table-fixed text-left text-sm">
                <thead className="bg-gray-50">
                <tr>
                    <th className="w-16 px-2 py-3 font-semibold text-center">
                        #
                    </th>
                    <th
                        className="w-32 px-4 py-3 font-semibold cursor-pointer"
                        onClick={() => handleSort("date")}
                    >
                        Datum {getSortIcon("date")}
                    </th>

                    <th
                        className="w-96 px-4 py-3 font-semibold cursor-pointer"
                        onClick={() => handleSort("description")}
                    >
                        Omschrijving {getSortIcon("description")}
                    </th>

                    <th
                        className="w-32 px-4 py-3 font-semibold text-right cursor-pointer"
                        onClick={() => handleSort("amount")}
                    >
                        Bedrag {getSortIcon("amount")}
                    </th>
                    <th
                        className="w-32 px-4 py-3 font-semibold cursor-pointer"
                        onClick={() => handleSort("category")}
                    >
                        Categorie {getSortIcon("category")}
                    </th>
                    <th className="w-32 px-4 py-3 font-semibold">Spaarrekening</th>
                </tr>
                </thead>
                <tbody>

                {sortedTransactions.map((t, index) => (
                    <React.Fragment key={t.id}>
                        <tr
                            onClick={() => setSelectedTx(t)}
                            className="border-t hover:bg-gray-50 cursor-pointer"
                        >
                            <td className="w-16 px-2 py-2 text-center text-gray-500 text-xs">
                                {index + 1}
                            </td>
                            <td className="w-32 px-4 py-2">{formatDate(t.date)}</td>
                            <td className="w-96 px-4 py-2">{t.description}</td>
                            <td className="w-32 px-4 py-2 text-right font-mono">
                                {t.transactionType === "debit" ? (
                                    <span className="text-red-500">- {formatMoney(t.amount)} ▼</span>
                                ) : (
                                    <span className="text-green-700">+ {formatMoney(t.amount)} ▲</span>
                                )}
                            </td>
                            <td className="w-32 px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                <CategoryCombobox
                                    transactionId={t.id}
                                    categoryId={t.category?.id ?? null}
                                    refresh={refresh}
                                    categories={categories}
                                    setCategories={setCategories}
                                    transactionType={t.transactionType}
                                />
                            </td>
                            <td className="w-32 px-4 py-2" onClick={(e) => e.stopPropagation()}>
                                <SavingsAccountCombobox
                                    transactionId={t.id}
                                    savingsAccountId={t.savingsAccount?.id ?? null}
                                    refresh={refresh}
                                    savingsAccounts={savingsAccounts}
                                    onCreate={addSavingsAccount}
                                />
                            </td>
                        </tr>
                    </React.Fragment>

                ))}
                </tbody>
            </table>
            {selectedTx && (
                <TransactionDrawer
                    transaction={selectedTx}
                    onClose={() => setSelectedTx(undefined)}
                    onFilterByDescription={onFilterByDescription}
                    onFilterByNotes={onFilterByNotes}
                />
            )}
        </div>
    );
}
