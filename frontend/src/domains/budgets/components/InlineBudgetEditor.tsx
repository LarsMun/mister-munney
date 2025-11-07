// frontend/src/domains/budgets/components/InlineBudgetEditor.tsx

import React, { useState, useEffect } from 'react';
import type { Budget } from '../models/Budget';
import { IconPicker } from '../../../shared/components/IconPicker';

interface InlineBudgetEditorProps {
    budget: Budget;
    onUpdateBudget: (budgetId: number, data: { name?: string; budgetType?: string; icon?: string | null }) => Promise<void>;
}

export function InlineBudgetEditor({ budget, onUpdateBudget }: InlineBudgetEditorProps) {
    const [isEditingName, setIsEditingName] = useState(false);
    const [tempName, setTempName] = useState(budget.name);
    const [isEditingIcon, setIsEditingIcon] = useState(false);
    const [isEditingType, setIsEditingType] = useState(false);

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

    const handleIconChange = async (icon: string | null) => {
        try {
            await onUpdateBudget(budget.id, { icon });
            setIsEditingIcon(false);
        } catch (error) {
            console.error('Error updating budget icon:', error);
        }
    };

    const handleTypeChange = async (newType: string) => {
        if (newType === budget.budgetType) {
            setIsEditingType(false);
            return;
        }

        try {
            await onUpdateBudget(budget.id, { budgetType: newType });
            setIsEditingType(false);
        } catch (error) {
            console.error('Error updating budget type:', error);
        }
    };

    const handleNameKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            saveName();
        } else if (e.key === 'Escape') {
            cancelNameEdit();
        }
    };

    const getBudgetTypeLabel = (type: string): string => {
        switch (type) {
            case 'EXPENSE': return 'Uitgaven';
            case 'INCOME': return 'Inkomsten';
            case 'PROJECT': return 'Project';
            default: return type;
        }
    };

    const getBudgetTypeColor = (type: string): string => {
        switch (type) {
            case 'EXPENSE': return 'text-red-600';
            case 'INCOME': return 'text-green-600';
            case 'PROJECT': return 'text-blue-600';
            default: return 'text-gray-600';
        }
    };

    return (
        <div className="space-y-3">
            {/* Icon Editor */}
            {isEditingIcon && (
                <div>
                    <IconPicker
                        selectedIcon={budget.icon}
                        onSelect={handleIconChange}
                        label="Budget Icoon"
                    />
                    <button
                        onClick={() => setIsEditingIcon(false)}
                        className="mt-2 text-sm text-gray-600 hover:text-gray-800"
                    >
                        Annuleren
                    </button>
                </div>
            )}

            {/* Icon Display & Edit Button */}
            {!isEditingIcon && (
                <div>
                    <button
                        onClick={() => setIsEditingIcon(true)}
                        className="text-xs text-blue-600 hover:text-blue-800 hover:underline"
                        title="Wijzig icoon"
                    >
                        {budget.icon ? '✏️ Wijzig icoon' : '+ Voeg icoon toe'}
                    </button>
                </div>
            )}

            {/* Budget Name */}
            <div>
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
                            title="Opslaan"
                        >
                            ✓
                        </button>
                        <button
                            onClick={cancelNameEdit}
                            className="text-red-600 hover:text-red-800 text-sm"
                            title="Annuleren"
                        >
                            ✕
                        </button>
                    </div>
                ) : (
                    <h3
                        className="text-xl font-bold cursor-pointer hover:underline text-gray-900 hover:text-blue-600"
                        onClick={startNameEdit}
                        title="Klik om naam te wijzigen"
                    >
                        {budget.name}
                    </h3>
                )}
            </div>

            {/* Budget Type */}
            <div>
                {isEditingType ? (
                    <div className="flex items-center space-x-2">
                        <select
                            value={budget.budgetType}
                            onChange={(e) => handleTypeChange(e.target.value)}
                            onBlur={() => setIsEditingType(false)}
                            autoFocus
                            className="px-2 py-1 border-2 border-blue-500 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="EXPENSE">Uitgaven</option>
                            <option value="INCOME">Inkomsten</option>
                            <option value="PROJECT">Project</option>
                        </select>
                        <button
                            onClick={() => setIsEditingType(false)}
                            className="text-red-600 hover:text-red-800 text-sm"
                            title="Annuleren"
                        >
                            ✕
                        </button>
                    </div>
                ) : (
                    <button
                        onClick={() => setIsEditingType(true)}
                        className={`text-sm font-medium cursor-pointer hover:underline ${getBudgetTypeColor(budget.budgetType)}`}
                        title="Klik om type te wijzigen"
                    >
                        {getBudgetTypeLabel(budget.budgetType)}
                    </button>
                )}
            </div>

            {/* Info Message */}
            <div className="text-xs text-gray-500 italic">
                💡 Dit budget is een container voor categorieën
            </div>
        </div>
    );
}
