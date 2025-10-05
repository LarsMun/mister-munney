//CategoryActions.ts

import { assignCategoryToTransaction, createCategory } from "./CategoryService.ts";
import { Category } from "../models/Category";
import toast from "react-hot-toast";
import React from "react";

export async function handleCategorySelect(
    category: Category | null,
    transactionId: number,
    accountId: number,
    refresh: () => void,
    setCategories: React.Dispatch<React.SetStateAction<Category[]>>,
    created?: boolean
) {
    try {
        await assignCategoryToTransaction(transactionId, accountId, category?.id ?? null);

        if (created && category) {
            setCategories(prev => [...prev, category]);
        }

        refresh();
    } catch (e) {
        const possibleError = category === null
            ? "Fout bij verwijderen van categorie uit transactie"
            : "Fout bij koppelen van categorie aan transactie";

        const errorMessage = e instanceof Error ? e.message : typeof e === 'string' ? e : 'Onbekende fout';

        console.error(possibleError, errorMessage);
        toast.error(`${possibleError}: ${errorMessage}`);
    }
}

export async function handleAddCategory(
    newCategoryData: Partial<Category>,
    accountId: number,
    setCategories: React.Dispatch<React.SetStateAction<Category[]>>
): Promise<Category> {
    const newCategory = await createCategory(accountId, newCategoryData);
    setCategories(prev => [...prev, newCategory]);
    return newCategory;
}