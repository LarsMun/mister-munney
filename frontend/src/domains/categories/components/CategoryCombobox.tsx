// src/domains/categories/components/CategoryCombobox.tsx

import { useState, useEffect, useRef, Dispatch, SetStateAction } from "react";
import type { Category } from "../models/Category";
import { useAccount } from "../../../app/context/AccountContext";
import { handleAddCategory } from "../services/CategoryActions";
import { getRandomPastelHex } from "../utils/categoryUtils";
import toast from "react-hot-toast";
import { assignCategoryToTransaction } from "../services/CategoryService";

interface Props {
    transactionId?: number;
    categoryId?: number | null;
    onChange?: (category: Category | null) => void;
    refresh?: () => void;
    categories: Category[];
    setCategories: Dispatch<SetStateAction<Category[]>>;
    transactionType?: 'debit' | 'credit';
}

export default function CategoryCombobox({
    transactionId,
    categoryId,
    onChange,
    refresh,
    categories,
    setCategories,
    transactionType
}: Props) {
    const { accountId } = useAccount();
    const inputRef = useRef<HTMLInputElement>(null);
    const [showList, setShowList] = useState(false);

    // Error boundary - als accountId null is, toon een loading state
    if (accountId === null) {
        return (
            <div className="w-full">
                <input
                    type="text"
                    className="w-full border rounded p-1 h-8 text-xs bg-gray-100"
                    placeholder="Laden..."
                    disabled
                />
            </div>
        );
    }

    // Safety check voor categories array
    const safeCategories = Array.isArray(categories) ? categories : [];

    const filteredByType = transactionType
        ? safeCategories.filter(c => c.transactionType === transactionType)
        : safeCategories;

    const selected = categoryId
        ? safeCategories.find((c) => c.id === categoryId) ?? null
        : null;

    const [input, setInput] = useState("");

    useEffect(() => {
        setInput(selected?.name ?? "");
    }, [selected?.id]);

    // Safety check voor filtering
    const filtered = input.trim() === ""
        ? filteredByType.slice(0, 10)
        : filteredByType.filter((c) =>
            c?.name?.toLowerCase()?.includes(input.toLowerCase())
        );

    const handleSelect = async (cat: Category) => {
        try {
            if (!cat || !cat.id) {
                console.error('Invalid category selected:', cat);
                toast.error('Ongeldige categorie geselecteerd');
                return;
            }

            setInput(cat.name);
            setShowList(false);
            onChange?.(cat);

            if (transactionId) {
                await assignCategoryToTransaction(accountId, transactionId, cat.id);
                refresh?.();
                toast.success("Categorie gekoppeld aan transactie");
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
                await assignCategoryToTransaction(accountId, transactionId, null);
                refresh?.();
                toast.success("Categorie verwijderd");
            }
        } catch (e) {
            console.error("Fout bij verwijderen:", e);
            toast.error("Fout bij verwijderen van categorie");
        }
    };

    const handleCreate = async () => {
        try {
            if (!input.trim()) return;

            // Validatie: transactionType moet aanwezig zijn
            if (!transactionType) {
                toast.error("Kan geen categorie aanmaken zonder transactietype");
                return;
            }

            const newCategory = await handleAddCategory(
                {
                    name: input.trim(),
                    color: getRandomPastelHex(),
                    icon: "tag",
                    transactionType
                },
                accountId,
                setCategories
            );

            if (newCategory) {
                await handleSelect(newCategory);
            }
        } catch (e) {
            console.error("Fout bij aanmaken categorie:", e);
            toast.error("Fout bij aanmaken nieuwe categorie");
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
                        placeholder="Categorie..."
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
                            {filtered.map((c) => (
                                <li
                                    key={c.id}
                                    className="px-2 py-1 hover:bg-gray-100 cursor-pointer"
                                    onClick={() => handleSelect(c)}
                                >
                                    <span
                                        className="inline-block px-2 py-1 rounded text-gray-700 text-sm"
                                        style={{ backgroundColor: c.color ?? "#CCCCCC" }}
                                    >
                                        {c.name}
                                    </span>
                                </li>
                            ))}
                            {input && !filteredByType.find(c => c?.name?.toLowerCase() === input.toLowerCase()) && (
                                <li
                                    className="px-2 py-1 text-blue-600 hover:bg-gray-50 cursor-pointer border-t"
                                    onClick={handleCreate}
                                >
                                    + Nieuwe categorie: "{input}"
                                </li>
                            )}
                        </ul>
                    )}
                </>
            )}
        </div>
    );
}
