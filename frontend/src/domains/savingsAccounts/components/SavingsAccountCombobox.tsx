import { useState, useEffect, useRef } from "react";
import type { SavingsAccount } from "../models/SavingsAccount";
import toast from "react-hot-toast";
import { assignSavingsAccountToTransaction } from "../services/SavingsAccountService.ts";
import {useRequiredAccount} from "../../../app/context/AccountContext.tsx";

interface Props {
    transactionId?: number;
    savingsAccountId?: number | null;
    onChange?: (sa: SavingsAccount | null) => void;
    refresh?: () => void;
    savingsAccounts: SavingsAccount[];
    onCreate: (newSavingsAccount: Partial<SavingsAccount>) => Promise<SavingsAccount>;
}

export default function SavingsAccountCombobox({
                                                   transactionId,
                                                   savingsAccountId,
                                                   onChange,
                                                   refresh,
                                                   savingsAccounts,
                                                   onCreate,
                                               }: Props) {
    const accountId = useRequiredAccount();
    const inputRef = useRef<HTMLInputElement>(null);
    const [showList, setShowList] = useState(false);

    // Safety check voor savingsAccounts array
    const safeSavingsAccounts = Array.isArray(savingsAccounts) ? savingsAccounts : [];

    const selected = savingsAccountId
        ? safeSavingsAccounts.find((s) => s.id === savingsAccountId) ?? null
        : null;

    const [input, setInput] = useState("");

    useEffect(() => {
        setInput(selected?.name ?? "");
    }, [selected?.id]);

    // Safety check voor filtering
    const filtered = input.trim() === ""
        ? safeSavingsAccounts.slice(0, 10)
        : safeSavingsAccounts.filter((s) =>
            s?.name?.toLowerCase()?.includes(input.toLowerCase())
        );

    const handleSelect = async (sa: SavingsAccount) => {
        try {
            if (!sa || !sa.id) {
                console.error('Invalid savings account selected:', sa);
                toast.error('Ongeldige spaarrekening geselecteerd');
                return;
            }

            setInput(sa.name);
            setShowList(false);
            onChange?.(sa);

            if (transactionId) {
                await assignSavingsAccountToTransaction(accountId, transactionId, sa.id);
                refresh?.();
                toast.success("Spaarrekening gekoppeld aan transactie");
            }
        } catch (e) {
            console.error("Fout bij koppelen:", e);
            toast.error("Fout bij koppelen aan transactie");
        }
    };

    const handleClear = async () => {
        try {
            setInput("");
            setShowList(false);
            onChange?.(null);

            if (transactionId) {
                await assignSavingsAccountToTransaction(accountId, transactionId, null);
                refresh?.();
                toast.success("Spaarrekening verwijderd");
            }
        } catch (e) {
            console.error("Fout bij verwijderen:", e);
            toast.error("Fout bij verwijderen van spaarrekening");
        }
    };

    const handleCreate = async () => {
        try {
            if (!input.trim()) return;
            const newSA = await onCreate({
                name: input.trim(),
                color: "#CCCCCC"
            });
            if (newSA) {
                await handleSelect(newSA);
            }
        } catch (e) {
            console.error("Fout bij aanmaken spaarrekening:", e);
            toast.error("Fout bij aanmaken nieuwe spaarrekening");
        }
    };

    return (
        <div className="relative w-full">
            {selected ? (
                <div
                    className="inline-flex items-center text-gray-700 text-xs font-semibold rounded overflow-hidden cursor-default"
                    style={{ backgroundColor: selected.color ?? "#CCCCCC" }}
                >
                    <span className="px-2 py-1">{selected.name}</span>
                    <span
                        className="px-2 py-1 cursor-pointer"
                        onClick={(e) => {
                            e.stopPropagation();
                            handleClear();
                        }}
                    >
                        ×
                    </span>
                </div>
            ) : (
                <>
                    <input
                        type="text"
                        ref={inputRef}
                        className="w-full border rounded p-1 h-8 text-xs"
                        placeholder="Spaarrekening..."
                        value={input}
                        onClick={() => {
                            setShowList(true);
                            inputRef.current?.select();
                        }}
                        onChange={(e) => {
                            setInput(e.target.value);
                            setShowList(true);
                        }}
                        onFocus={() => setShowList(true)}
                        onBlur={() => setTimeout(() => setShowList(false), 100)}
                    />
                    {showList && (
                        <ul className="absolute z-10 bg-white border mt-1 w-full max-h-40 overflow-auto rounded shadow text-xs">
                            {filtered.map((s) => (
                                <li
                                    key={s.id}
                                    className="px-2 py-1 hover:bg-gray-100 cursor-pointer"
                                    onClick={() => handleSelect(s)}
                                >
                                    <span
                                        className="inline-block px-2 py-1 rounded text-gray-700 text-sm"
                                        style={{ backgroundColor: s.color ?? "#CCCCCC" }}
                                    >
                                        {s.name}
                                    </span>
                                </li>
                            ))}
                            {input && !safeSavingsAccounts.find(s => s?.name?.toLowerCase() === input.toLowerCase()) && (
                                <li
                                    className="px-2 py-1 text-blue-600 hover:bg-gray-50 cursor-pointer border-t"
                                    onClick={handleCreate}
                                >
                                    + Nieuwe spaarrekening: "{input}"
                                </li>
                            )}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
