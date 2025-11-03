// frontend/src/domains/budgets/components/BudgetVersionListItem.tsx

import React, { useState } from 'react';
import type { BudgetVersion, UpdateBudgetVersion } from '../models/Budget';
import { MonthPicker } from '../../../shared/components/MonthPicker';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface BudgetVersionListItemProps {
    version: BudgetVersion;
    canDelete: boolean;
    onUpdate?: (versionId: number, data: UpdateBudgetVersion) => Promise<void>;
    onDelete: (versionId: number) => void;
}

export function BudgetVersionListItem({ version, canDelete, onUpdate, onDelete }: BudgetVersionListItemProps) {
    const [isEditingAmount, setIsEditingAmount] = useState(false);
    const [isEditingFromDate, setIsEditingFromDate] = useState(false);
    const [isEditingUntilDate, setIsEditingUntilDate] = useState(false);
    const [tempAmount, setTempAmount] = useState(Math.abs(version.monthlyAmount).toString());
    const [tempFromDate, setTempFromDate] = useState(version.effectiveFromMonth);
    const [tempUntilDate, setTempUntilDate] = useState(version.effectiveUntilMonth || '');

    const handleAmountSave = async () => {
        if (!onUpdate) return;

        const numAmount = parseFloat(tempAmount);
        if (isNaN(numAmount) || numAmount === Math.abs(version.monthlyAmount)) {
            setIsEditingAmount(false);
            return;
        }

        try {
            // Preserve the sign (negative for expenses, positive for income)
            const signedAmount = version.monthlyAmount < 0 ? -Math.abs(numAmount) : Math.abs(numAmount);
            await onUpdate(version.id, { monthlyAmount: signedAmount });
            setIsEditingAmount(false);
        } catch (error) {
            console.error('Error updating amount:', error);
            setTempAmount(Math.abs(version.monthlyAmount).toString());
        }
    };

    const handleFromDateSave = async (newDate: string) => {
        if (!onUpdate) return;

        if (newDate && newDate !== version.effectiveFromMonth && /^\d{4}-\d{2}$/.test(newDate)) {
            try {
                await onUpdate(version.id, { effectiveFromMonth: newDate });
                setTempFromDate(newDate);
                setIsEditingFromDate(false);
            } catch (error) {
                console.error('Error updating from date:', error);
            }
        } else {
            setIsEditingFromDate(false);
        }
    };

    const handleUntilDateSave = async (newDate: string) => {
        if (!onUpdate) return;

        const newUntilDate = newDate.trim() === '' ? null : newDate;
        if (newUntilDate !== version.effectiveUntilMonth) {
            if (!newUntilDate || /^\d{4}-\d{2}$/.test(newUntilDate)) {
                try {
                    await onUpdate(version.id, { effectiveUntilMonth: newUntilDate || undefined });
                    setTempUntilDate(newUntilDate || '');
                    setIsEditingUntilDate(false);
                } catch (error) {
                    console.error('Error updating until date:', error);
                }
            }
        } else {
            setIsEditingUntilDate(false);
        }
    };

    const handleAmountKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleAmountSave();
        } else if (e.key === 'Escape') {
            setIsEditingAmount(false);
            setTempAmount(Math.abs(version.monthlyAmount).toString());
        }
    };

    return (
        <div
            className={`flex justify-between items-start p-2 rounded ${
                version.isCurrent ? 'bg-green-50 border border-green-200' : 'bg-white'
            }`}
        >
            <div className="flex-1">
                <div className="flex items-center space-x-2">
                    {/* Editable Amount */}
                    {isEditingAmount ? (
                        <div className="flex items-center space-x-1">
                            <span className="text-gray-700">€</span>
                            <input
                                type="number"
                                step="0.01"
                                value={tempAmount}
                                onChange={(e) => setTempAmount(e.target.value)}
                                onKeyDown={handleAmountKeyDown}
                                onBlur={handleAmountSave}
                                autoFocus
                                className="w-24 px-1 py-0.5 border border-blue-500 rounded focus:outline-none focus:ring-1 focus:ring-blue-500"
                            />
                            <button
                                onClick={handleAmountSave}
                                className="text-green-600 hover:text-green-800 text-xs"
                                title="Opslaan"
                            >
                                ✓
                            </button>
                            <button
                                onClick={() => {
                                    setIsEditingAmount(false);
                                    setTempAmount(Math.abs(version.monthlyAmount).toString());
                                }}
                                className="text-red-600 hover:text-red-800 text-xs"
                                title="Annuleren"
                            >
                                ✕
                            </button>
                        </div>
                    ) : (
                        <span
                            className="font-medium text-gray-900 cursor-pointer hover:text-blue-600 hover:underline"
                            onClick={() => onUpdate && setIsEditingAmount(true)}
                            title={onUpdate ? "Klik om bedrag te wijzigen" : undefined}
                        >
                            {formatMoney(Math.abs(version.monthlyAmount))}
                        </span>
                    )}

                    {version.isCurrent && (
                        <span className="text-xs bg-green-100 text-green-800 px-2 py-0.5 rounded">
                            Actief
                        </span>
                    )}
                </div>

                {/* Editable Date Range */}
                <div className="text-xs text-gray-600 mt-1 flex items-center gap-1 flex-wrap">
                    {/* From Date */}
                    {isEditingFromDate ? (
                        <MonthPicker
                            value={tempFromDate}
                            onChange={handleFromDateSave}
                            autoFocus={true}
                            onBlur={() => setIsEditingFromDate(false)}
                        />
                    ) : (
                        <span
                            className={onUpdate ? "cursor-pointer hover:text-blue-600 hover:underline" : ""}
                            onClick={() => onUpdate && setIsEditingFromDate(true)}
                            title={onUpdate ? "Klik om startdatum te wijzigen" : undefined}
                        >
                            {version.effectiveFromMonth}
                        </span>
                    )}

                    <span>tot</span>

                    {/* Until Date */}
                    {isEditingUntilDate ? (
                        <MonthPicker
                            value={tempUntilDate}
                            onChange={handleUntilDateSave}
                            autoFocus={true}
                            allowEmpty={true}
                            placeholder="open"
                            onBlur={() => setIsEditingUntilDate(false)}
                        />
                    ) : (
                        <span
                            className={onUpdate ? "cursor-pointer hover:text-blue-600 hover:underline" : ""}
                            onClick={() => onUpdate && setIsEditingUntilDate(true)}
                            title={onUpdate ? "Klik om einddatum te wijzigen" : undefined}
                        >
                            {version.effectiveUntilMonth || 'open'}
                        </span>
                    )}
                </div>

                {version.changeReason && (
                    <div className="text-xs text-gray-500 mt-1 italic">
                        "{version.changeReason}"
                    </div>
                )}
            </div>

            {/* Delete button */}
            <button
                onClick={() => onDelete(version.id)}
                disabled={!canDelete}
                className={`ml-2 text-xs px-2 py-1 rounded transition-colors ${
                    !canDelete
                        ? 'text-gray-300 cursor-not-allowed'
                        : 'text-red-600 hover:bg-red-50 hover:text-red-700'
                }`}
                title={!canDelete ? 'Kan laatste versie niet verwijderen' : 'Verwijder versie'}
            >
                🗑️
            </button>
        </div>
    );
}
