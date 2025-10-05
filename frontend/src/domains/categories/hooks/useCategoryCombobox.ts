// src/domains/categories/hooks/useCategoryCombobox.ts

import React, { useEffect, useRef, useState, useMemo, useCallback } from 'react';
import { getRandomPastelHex } from '../utils/categoryUtils';
import type { Category } from '../models/Category';

export type Props = {
    transactionId?: number;
    currentCategory: Category | null;
    accountId: number;
    onSelect: (category: Category | null, transactionId: number, accountId: number, created?: boolean) => void;
    categories: Category[];
    onCreateCategory: (newCategory: Partial<Category>, accountId: number) => Promise<Category>;
};

export function useCategoryCombobox({
                                        transactionId,
                                        currentCategory,
                                        accountId,
                                        categories,
                                        onSelect,
                                        onCreateCategory,
                                    }: Props) {
    const [inputValue, setInputValue] = useState('');
    const [showList, setShowList] = useState(false);
    const [highlightedIndex, setHighlightedIndex] = useState(-1);

    const wrapperRef = useRef<HTMLDivElement>(null!);
    const inputRef = useRef<HTMLInputElement>(null!);

    function isClickOutside(ref: React.RefObject<HTMLElement>, event: MouseEvent) {
        return ref.current && !ref.current.contains(event.target as Node);
    }

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (isClickOutside(wrapperRef, event)) {
                setShowList(false);
                if (inputValue.trim() !== '') {
                    setInputValue('');
                }
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [inputValue]);

    const sortedFiltered = useMemo(() => {
        return categories
            .filter(c => {
                if (inputValue.trim() === '') return true;
                const search = inputValue.trim().toLowerCase();
                const name = c.name.toLowerCase();
                const words = name.split(' ');

                return (
                    words.some(word => word.startsWith(search)) ||
                    name.includes(search)
                );
            })
            .sort((a, b) => a.name.localeCompare(b.name))
            .slice(0, 8);
    }, [categories, inputValue]);

    const handleCreateCategory = useCallback(async () => {
        if (!inputValue.trim()) return;
        try {
            const newCategory = await onCreateCategory({
                name: inputValue,
                icon: 'tag',
                color: getRandomPastelHex(),
            }, accountId);

            if (transactionId !== undefined) {
                onSelect(newCategory, transactionId, accountId, true);
            } else {
                onSelect(newCategory, 0, accountId, true); // 👈 Bijvoorbeeld 0 als "bulk"
            }

            setInputValue('');
            setShowList(false);
        } catch (error) {
            console.error('Fout bij aanmaken nieuwe categorie:', error);
        }
    }, [inputValue, onCreateCategory, onSelect, accountId, transactionId]);

    const handleKeyDown = useCallback(async (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightedIndex(prev => (prev + 1) % sortedFiltered.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightedIndex(prev => (prev - 1 + sortedFiltered.length) % sortedFiltered.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();

            if (highlightedIndex >= 0 && sortedFiltered[highlightedIndex]) {
                const selected = sortedFiltered[highlightedIndex];
                if (transactionId !== undefined) {
                    onSelect(selected, transactionId, accountId);
                } else {
                    onSelect(selected, 0, accountId);
                }

                setInputValue(selected.name);
                setShowList(false);
                setHighlightedIndex(-1);
            } else {
                const exactMatch = categories.find(
                    (c) => c.name.toLowerCase() === inputValue.trim().toLowerCase()
                );

                if (exactMatch) {
                    if (transactionId !== undefined) {
                        onSelect(exactMatch, transactionId, accountId);
                    } else {
                        onSelect(exactMatch, 0, accountId);
                    }
                    setInputValue(exactMatch.name);
                    setShowList(false);
                    setHighlightedIndex(-1);
                } else {
                    await handleCreateCategory();
                }
            }
        }
    }, [highlightedIndex, sortedFiltered, categories, inputValue, onSelect, accountId, transactionId, handleCreateCategory]);


    return {
        inputValue,
        setInputValue,
        showList,
        setShowList,
        highlightedIndex,
        setHighlightedIndex,
        wrapperRef,
        inputRef,
        sortedFiltered,
        handleKeyDown,
        handleCreateCategory,
    };
}