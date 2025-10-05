// src/domains/patterns/components/PatternList.tsx

import React, { useEffect, useState } from "react";
import { PatternDTO } from "../models/PatternDTO";
import { getPatternsForAccount, deletePattern } from "../services/PatternService";
import { useCategories } from "../../categories/hooks/useCategories";
import { useSavingsAccounts } from "../../savingsAccounts/hooks/useSavingsAccounts";
import { useRequiredAccount } from "../../../app/context/AccountContext";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import { formatDateFullMonthName } from "../../../shared/utils/DateFormat";
import { Pencil, Trash2 } from "lucide-react";
import { toast } from "react-hot-toast";
import { useConfirmDialog } from "../../../shared/hooks/useConfirmDialog";

interface Props {
    resetSignal?: number;
}

export default function PatternList(resetSignal) {
    const accountId = useRequiredAccount()

    useEffect(() => {
        (async () => {
            await refresh();
        })();
    }, [accountId]);

    useEffect(() => {
        refresh();
    }, [accountId, resetSignal]);

    const [patterns, setPatterns] = useState<PatternDTO[]>([]);
    const [loading, setLoading] = useState(true);
    const { confirm, Confirm } = useConfirmDialog();

    const refresh = async () => {
        if (!accountId) return;
        setLoading(true);
        try {
            const result = await getPatternsForAccount(accountId);
            setPatterns(result);
        } catch {
            toast.error("Kon patronen niet ophalen.");
        } finally {
            setLoading(false);
        }
    };

    const handleDelete = async (pattern: PatternDTO) => {
        const ok = await confirm({
            title: "Patroon verwijderen?",
            description: pattern.description || "Weet je zeker dat je dit patroon wilt verwijderen?",
        });

        if (!ok) return;

        try {
            await deletePattern(accountId, pattern.id);
            toast.success("Patroon verwijderd.");
            await refresh();
        } catch {
            toast.error("Verwijderen mislukt.");
        }
    };

    const handleEdit = (pattern: PatternDTO) => {
        console.log("Bewerk patroon:", pattern);
    };

    const getTargetElement = (p: PatternDTO): React.ReactNode => {
        const parts: React.ReactNode[] = [];

        if (p.category?.id) {
            parts.push(
                p.category.name ? (
                    <>
                        categorie{" "}
                        <span
                            className="inline-block px-2 py-0.5 rounded text-xs font-semibold text-gray-800"
                            style={{ backgroundColor: p.category.color ?? "#e5e7eb" }}
                        >
                    {p.category.name}
                </span>
                    </>
                ) : (
                    <>categorie <span className="italic text-gray-400">(onbekend)</span></>
                )
            );
        } else {
            console.log("geen categorie gevonden");
        }

        if (p.savingsAccount?.id) {
            parts.push(
                p.savingsAccount.name ? (
                    <>
                        spaarrekening{" "}
                        <span className="inline-block px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-800 font-medium">
                    {p.savingsAccount.name}
                </span>
                    </>
                ) : (
                    <>spaarrekening <span className="italic text-gray-400">(onbekend)</span></>
                )
            );
        }

        if (parts.length === 0) {
            return <span className="text-gray-400">–</span>;
        }

        return (
            <span>
                Koppelt aan{" "}
                {parts.map((part, i) => (
                    <React.Fragment key={i}>
                        {i > 0 && " en aan "}
                        {part}
                    </React.Fragment>
                ))}
            </span>
        );
    };

    if (loading) return <p>Laden...</p>;
    if (patterns.length === 0) return <p>Geen patronen gevonden.</p>;

    return (
        <>
            <div className="space-y-2">
                {patterns.map((p) => (
                    <div key={p.id} className="relative group border p-3 rounded bg-white shadow-sm hover:shadow transition">
                        <div className="absolute top-2 right-2 flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button
                                onClick={() => handleEdit(p)}
                                className="p-1 text-gray-500 hover:text-blue-600"
                                title="Bewerken"
                            >
                                <Pencil size={16} />
                            </button>
                            <button
                                onClick={() => handleDelete(p)}
                                className="p-1 text-gray-500 hover:text-red-600"
                                title="Verwijderen"
                            >
                                <Trash2 size={16} />
                            </button>
                        </div>

                        <div className="text-sm font-semibold text-gray-800 mb-1">
                            {p.description || <span className="italic text-gray-400">(geen omschrijving)</span>}
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-3 gap-y-1 text-xs text-gray-600">
                            <div>{getTargetElement(p)}</div>
                            <div>
                                <span className="font-medium text-gray-500">Bedrag:</span>{" "}
                                {p.minAmount !== undefined ? formatMoney(p.minAmount) : "–"} –{" "}
                                {p.maxAmount !== undefined ? formatMoney(p.maxAmount) : "∞"}
                            </div>
                            {(p.startDate || p.endDate) && (
                                <div>
                                    <span className="font-medium text-gray-500">Periode:</span>{" "}
                                    {p.startDate ? formatDateFullMonthName(p.startDate) : "–"} t/m{" "}
                                    {p.endDate ? formatDateFullMonthName(p.endDate) : "–"}
                                </div>
                            )}
                            {p.notes && (
                                <div className="col-span-2">
                                    <span className="font-medium text-gray-500">Notities:</span> {p.notes}
                                </div>
                            )}
                            {p.tag && (
                                <div>
                                    <span className="font-medium text-gray-500">Tag:</span> {p.tag}
                                </div>
                            )}
                            <div>
                                <span className="font-medium text-gray-500">Strict:</span> {p.strict ? "Ja" : "Nee"}
                            </div>
                        </div>
                    </div>
                ))}
            </div>
            {Confirm}
        </>
    );
}