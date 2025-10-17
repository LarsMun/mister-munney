// frontend/src/domains/budgets/components/InlineBudgetEditor.tsx

import React, { useState, useEffect } from 'react';
import type { Budget, UpdateBudgetVersion } from '../models/Budget';
import { MonthPicker } from '../../../shared/components/MonthPicker';

interface InlineBudgetEditorProps {
    budget: Budget;
    onUpdateBudget: (budgetId: number, data: { name: string }) => Promise<void>;
    onUpdateVersion?: (budgetId: number, versionId: number, data: UpdateBudgetVersion) => Promise<void>;
    isOverBudget?: boolean;
}

export function InlineBudgetEditor({ budget, onUpdateBudget, onUpdateVersion, isOverBudget }: InlineBudgetEditorProps) {
    const [isEditingName, setIsEditingName] = useState(false);
    const [tempName, setTempName] = useState(budget.name);
    const [isEditingFromDate, setIsEditingFromDate] = useState(false);
    const [isEditingUntilDate, setIsEditingUntilDate] = useState(false);
    const [tempFromDate, setTempFromDate] = useState('');
    const [tempUntilDate, setTempUntilDate] = useState('');

    // Get the currently active version, or fallback to the newest version
    const activeVersion = budget.versions.find(v => v.isCurrent) ||
        budget.versions.sort((a, b) =>
            b.effectiveFromMonth.localeCompare(a.effectiveFromMonth)
        )[0];

    useEffect(() => {
        setTempName(budget.name);
    }, [budget.name]);

    const startNameEdit = () => {
        setIsEditingName(true);
        setTempName(budget.name);
    };

    const saveName = async () => {
        if (tempName.trim() === '' || tempName === budget.name) {
            cancelNameEdit();
            return;
        }

        try {
            await onUpdateBudget(budget.id, { name: tempName });
            setIsEditingName(false);
        } catch (error) {
            console.error('Error updating budget name:', error);
        }
    };

    const cancelNameEdit = () => {
        setIsEditingName(false);
        setTempName(budget.name);
    };

    const handleFromDateChange = async (newDate: string) => {
        setTempFromDate(newDate);

        if (!activeVersion || !onUpdateVersion) return;

        // Validate and save immediately
        if (newDate && newDate !== activeVersion.effectiveFromMonth && /^\d{4}-\d{2}$/.test(newDate)) {
            try {
                await onUpdateVersion(budget.id, activeVersion.id, {
                    effectiveFromMonth: newDate
                });
                setIsEditingFromDate(false);
            } catch (error) {
                console.error('Error updating from date:', error);
            }
        }
    };

    const handleUntilDateChange = async (newDate: string) => {
        setTempUntilDate(newDate);

        if (!activeVersion || !onUpdateVersion) return;

        const newUntilDate = newDate.trim() === '' ? null : newDate;

        // Save immediately if valid
        if (newUntilDate !== activeVersion.effectiveUntilMonth) {
            if (!newUntilDate || /^\d{4}-\d{2}$/.test(newUntilDate)) {
                try {
                    await onUpdateVersion(budget.id, activeVersion.id, {
                        effectiveUntilMonth: newUntilDate || undefined
                    });
                    setIsEditingUntilDate(false);
                } catch (error) {
                    console.error('Error updating until date:', error);
                }
            }
        }
    };

    const startFromDateEdit = () => {
        if (!activeVersion || !onUpdateVersion || budget.versions.length === 0) return;
        setIsEditingFromDate(true);
        setTempFromDate(activeVersion.effectiveFromMonth);
    };

    const startUntilDateEdit = () => {
        if (!activeVersion || !onUpdateVersion || budget.versions.length === 0) return;
        setIsEditingUntilDate(true);
        setTempUntilDate(activeVersion.effectiveUntilMonth || '');
    };

    const cancelFromDateEdit = () => {
        setIsEditingFromDate(false);
    };

    const cancelUntilDateEdit = () => {
        setIsEditingUntilDate(false);
    };

    const handleNameKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            saveName();
        } else if (e.key === 'Escape') {
            cancelNameEdit();
        }
    };

    const formatMoney = (amount: number): string => {
        return new Intl.NumberFormat('nl-NL', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    };

    return (
        <div className="mb-4">
            {/* Budget Name */}
            <div className="mb-2">
                {isEditingName ? (
                    <div className="flex items-center space-x-2">
                        <input
                            type="text"
                            value={tempName}
                            onChange={(e) => setTempName(e.target.value)}
                            onKeyDown={handleNameKeyDown}
                            onBlur={cancelNameEdit}
                            autoFocus
                            className="text-xl font-bold bg-transparent border-b-2 border-blue-500 focus:outline-none flex-1"
                        />
                        <button
                            onClick={saveName}
                            className="text-green-600 hover:text-green-800 text-sm"
                        >
                            ✓
                        </button>
                        <button
                            onClick={cancelNameEdit}
                            className="text-red-600 hover:text-red-800 text-sm"
                        >
                            ✕
                        </button>
                    </div>
                ) : (
                    <div className="flex items-center space-x-2">
                        {isOverBudget && <span className="text-red-600">⚠️</span>}
                        <h3
                            className={`text-xl font-bold cursor-pointer hover:underline ${
                                isOverBudget ? 'text-red-600 hover:text-red-700' : 'text-gray-900 hover:text-blue-600'
                            }`}
                            onClick={startNameEdit}
                            title="Klik om naam te wijzigen"
                        >
                            {budget.name}
                        </h3>
                    </div>
                )}
            </div>

            {/* Current Budget Info */}
            <div className="text-2xl font-bold text-gray-900">
                {activeVersion ? formatMoney(Math.abs(activeVersion.monthlyAmount)) : '€ 0,00'}
            </div>

            {/* Editable Date Range */}
            <div className="text-sm text-gray-500 mt-1 flex items-center flex-wrap gap-1">
                <span>{budget.budgetType === 'INCOME' ? 'Doel' : 'Limiet'} per maand vanaf</span>

                {/* From Date - Editable */}
                {isEditingFromDate ? (
                    <MonthPicker
                        value={tempFromDate}
                        onChange={handleFromDateChange}
                        autoFocus={true}
                        onBlur={cancelFromDateEdit}
                    />
                ) : (
                    <span
                        className="cursor-pointer hover:text-blue-600 hover:underline font-medium"
                        onClick={startFromDateEdit}
                        title="Klik om startdatum te wijzigen"
                    >
                        {activeVersion?.effectiveFromMonth || 'niet ingesteld'}
                    </span>
                )}

                {/* Until Date - Editable or "nu" */}
                {isEditingUntilDate ? (
                    <>
                        <span>-</span>
                        <MonthPicker
                            value={tempUntilDate}
                            onChange={handleUntilDateChange}
                            autoFocus={true}
                            allowEmpty={true}
                            placeholder="Geen einde"
                            onBlur={cancelUntilDateEdit}
                        />
                    </>
                ) : (
                    <>
                        <span>-</span>
                        <span
                            className="cursor-pointer hover:text-blue-600 hover:underline font-medium"
                            onClick={startUntilDateEdit}
                            title="Klik om einddatum te wijzigen"
                        >
                            {activeVersion?.effectiveUntilMonth || 'nu'}
                        </span>
                    </>
                )}
            </div>
        </div>
    );
}
