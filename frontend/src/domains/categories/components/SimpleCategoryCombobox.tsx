import { useState, useEffect, useRef } from "react";
import { useCategories } from "../hooks/useCategories.ts";
import { handleAddCategory } from "../services/CategoryActions.ts";
import type { Category } from "../models/Category.ts";
import { useAccount } from "../../../app/context/AccountContext.tsx";
import {getRandomPastelHex} from "../utils/categoryUtils.ts";

interface Props {
    categoryId: number | null;
    onChange: (category: Category | null) => void;
    refreshCategories: () => void;
    transactionType?: 'debit' | 'credit';  // <-- NIEUW
}

export default function SimpleCategoryCombobox({
                                                   categoryId,
                                                   onChange,
                                                   transactionType  // <-- NIEUW
                                               }: Props) {
    const { accountId } = useAccount();
    if (accountId === null) return null;
    const { categories, setCategories } = useCategories(accountId);
    const inputRef = useRef<HTMLInputElement>(null);
    const [showList, setShowList] = useState(false);

    const selectedCategory = categoryId ? categories.find(c => c.id === categoryId) ?? null : null;
    const [input, setInput] = useState("");

    // Prefill input alleen bij laden of wissel van categorie
    useEffect(() => {
        if (selectedCategory) {
            setInput(selectedCategory.name);
        } else {
            setInput("");
        }
    }, [selectedCategory?.id]);

    // NIEUW: Filter categorieën op basis van transactionType
    const filteredByType = transactionType
        ? categories.filter(c => c.transactionType === transactionType)
        : categories;

    const filtered = input.trim() === ""
        ? filteredByType.slice(0, 10) // default 10 tonen
        : filteredByType.filter((c) =>
            c.name.toLowerCase().includes(input.toLowerCase())
        );

    const handleSelect = (category: Category) => {
        setInput(category.name);
        onChange(category);
        setShowList(false);
    };

    const handleCreate = async () => {
        if (!input.trim()) return;

        // NIEUW: Validatie
        if (!transactionType) {
            console.error("Kan geen categorie aanmaken zonder transactionType");
            return;
        }

        const newCategory = await handleAddCategory(
            {
                name: input,
                color: getRandomPastelHex(),
                transactionType  // <-- NIEUW
            },
            accountId,
            setCategories
        );
        setInput(newCategory.name);
        onChange(newCategory);
        setShowList(false);
    };

    const handleClear = () => {
        setInput("");
        onChange(null);
        setShowList(false);
    };

    return (
        <div className="relative w-full">
            {selectedCategory ? (
                <div
                    className="inline-flex items-center text-gray-700 text-xs font-semibold rounded overflow-hidden cursor-default"
                    style={{ backgroundColor: selectedCategory.color }}
                >
                    <span className="px-2 py-1">{selectedCategory.name}</span>
                    <span
                        className="px-2 py-1 cursor-pointer"
                        style={{ backgroundColor: selectedCategory.color }}
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
                        onFocus={() => {
                            setShowList(true);
                        }}
                        onBlur={() => {
                            setTimeout(() => setShowList(false), 100);
                        }}
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
                                        style={{ backgroundColor: c.color }}
                                    >
                                        {c.name}
                                    </span>
                                </li>
                            ))}
                            {/* AANGEPAST: gebruik filteredByType in plaats van categories */}
                            {input && !filteredByType.find(c => c.name.toLowerCase() === input.toLowerCase()) && (
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