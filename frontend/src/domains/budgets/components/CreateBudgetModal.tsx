import React, { useState } from 'react';
import type { CreateBudget } from '../models/Budget';

interface CreateBudgetModalProps {
    isOpen: boolean;
    onClose: () => void;
    onCreate: (budget: CreateBudget) => Promise<void>;
    accountId: number;
}

export function CreateBudgetModal({ isOpen, onClose, onCreate, accountId }: CreateBudgetModalProps) {
    const [formData, setFormData] = useState<CreateBudget>({
        name: '',
        accountId,
        budgetType: 'EXPENSE',
        monthlyAmount: 0,
        effectiveFromMonth: new Date().toISOString().slice(0, 7), // YYYY-MM format
        changeReason: '',
        categoryIds: []
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const validate = () => {
        const newErrors: Record<string, string> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'Naam is verplicht';
        }

        if (!formData.monthlyAmount || formData.monthlyAmount <= 0) {
            newErrors.monthlyAmount = 'Bedrag moet groter dan 0 zijn';
        }

        if (!formData.effectiveFromMonth) {
            newErrors.effectiveFromMonth = 'Startdatum is verplicht';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!validate()) return;

        setIsSubmitting(true);
        try {
            await onCreate(formData);
            handleClose();
        } catch (error) {
            // Error is handled by the action
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        setFormData({
            name: '',
            accountId,
            budgetType: 'EXPENSE',
            monthlyAmount: 0,
            effectiveFromMonth: new Date().toISOString().slice(0, 7),
            changeReason: '',
            categoryIds: []
        });
        setErrors({});
        onClose();
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg p-6 w-full max-w-md mx-4">
                <h2 className="text-xl font-bold mb-4">Nieuw Budget Aanmaken</h2>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Budget Naam
                        </label>
                        <input
                            type="text"
                            value={formData.name}
                            onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Bijv. Boodschappen"
                            disabled={isSubmitting}
                        />
                        {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Budget Type
                        </label>
                        <select
                            value={formData.budgetType}
                            onChange={(e) => setFormData(prev => ({ ...prev, budgetType: e.target.value as 'EXPENSE' | 'INCOME' }))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            disabled={isSubmitting}
                        >
                            <option value="EXPENSE">Uitgaven</option>
                            <option value="INCOME">Inkomsten</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Maandelijks {formData.budgetType === 'INCOME' ? 'Doel' : 'Limiet'} (€)
                        </label>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            value={formData.monthlyAmount || ''}
                            onChange={(e) => setFormData(prev => ({
                                ...prev,
                                monthlyAmount: parseFloat(e.target.value) || 0
                            }))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="500.00"
                            disabled={isSubmitting}
                        />
                        {errors.monthlyAmount && <p className="text-red-500 text-sm mt-1">{errors.monthlyAmount}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Startdatum
                        </label>
                        <input
                            type="month"
                            value={formData.effectiveFromMonth}
                            onChange={(e) => setFormData(prev => ({
                                ...prev,
                                effectiveFromMonth: e.target.value
                            }))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            disabled={isSubmitting}
                        />
                        {errors.effectiveFromMonth && <p className="text-red-500 text-sm mt-1">{errors.effectiveFromMonth}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Reden (optioneel)
                        </label>
                        <textarea
                            value={formData.changeReason || ''}
                            onChange={(e) => setFormData(prev => ({
                                ...prev,
                                changeReason: e.target.value
                            }))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            rows={3}
                            placeholder="Waarom dit budget..."
                            disabled={isSubmitting}
                        />
                    </div>

                    <div className="flex justify-end space-x-3 mt-6">
                        <button
                            type="button"
                            onClick={handleClose}
                            className="px-4 py-2 text-gray-700 border border-gray-300 rounded-md hover:bg-gray-50"
                            disabled={isSubmitting}
                        >
                            Annuleren
                        </button>
                        <button
                            type="submit"
                            className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Aanmaken...' : 'Budget Aanmaken'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}