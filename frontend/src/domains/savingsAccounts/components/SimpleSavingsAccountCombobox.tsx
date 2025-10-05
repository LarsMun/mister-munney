import React, { useState, useEffect, useRef } from "react";
import { useSavingsAccounts } from "../hooks/useSavingsAccounts";
import type { SavingsAccount } from "../models/SavingsAccount";
import { useAccount } from "../../../app/context/AccountContext";

interface Props {
    savingsAccountId: number | null;
    onChange: (savingsAccount: SavingsAccount | null) => void;
}

export default function SimpleSavingsAccountCombobox({ savingsAccountId, onChange }: Props) {
    const { accountId } = useAccount();
    if (accountId === null) return null;

    const { savingsAccounts, addSavingsAccount } = useSavingsAccounts(accountId);
    const inputRef = useRef<HTMLInputElement>(null);
    const [showList, setShowList] = useState(false);

    const selectedSavingsAccount = savingsAccountId
        ? savingsAccounts.find(sa => sa.id === savingsAccountId) ?? null
        : null;
    const [input, setInput] = useState("");

    useEffect(() => {
        if (selectedSavingsAccount) {
            setInput(selectedSavingsAccount.name);
        } else {
            setInput("");
        }
    }, [selectedSavingsAccount?.id]);

    const filtered = input.trim() === ""
        ? savingsAccounts.slice(0, 10)
        : savingsAccounts.filter((sa) =>
            sa.name.toLowerCase().includes(input.toLowerCase())
        );

    const handleSelect = (savingsAccount: SavingsAccount) => {
        setInput(savingsAccount.name);
        onChange(savingsAccount);
        setShowList(false);
    };

    const handleCreate = async () => {
        if (!input.trim()) return;
        const newSavingsAccount = await addSavingsAccount({
            name: input.trim(),
            color: "#CCCCCC"
        });
        setInput(newSavingsAccount.name);
        onChange(newSavingsAccount);
        setShowList(false);
    };

    const handleClear = () => {
        setInput("");
        onChange(null);
        setShowList(false);
    };

    return (
        <div className="relative w-full">
            {selectedSavingsAccount ? (
                <div
                    className="inline-flex items-center text-gray-700 text-xs font-semibold rounded overflow-hidden cursor-default"
                    style={{ backgroundColor: selectedSavingsAccount.color }}
                >
                    <span className="px-2 py-1">{selectedSavingsAccount.name}</span>
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
                            {filtered.map((sa) => (
                                <li
                                    key={sa.id}
                                    className="px-2 py-1 hover:bg-gray-100 cursor-pointer"
                                    onClick={() => handleSelect(sa)}
                                >
                                    <span
                                        className="inline-block px-2 py-1 rounded text-gray-700 text-sm"
                                        style={{ backgroundColor: sa.color }}
                                    >
                                        {sa.name}
                                    </span>
                                </li>
                            ))}
                            {input && !savingsAccounts.find(sa => sa.name.toLowerCase() === input.toLowerCase()) && (
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