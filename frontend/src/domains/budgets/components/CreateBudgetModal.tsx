import React, { useState } from 'react';
import type { CreateBudget } from '../models/Budget';
import { IconPicker } from '../../../shared/components/IconPicker';

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
        icon: null,
        categoryIds: []
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const validate = () => {
        const newErrors: Record<string, string> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'Naam is verplicht';
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
            icon: null,
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
                <p className="text-sm text-gray-600 mb-4">
                    Maak een container aan om categorieën te groeperen
                </p>

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
                            onChange={(e) => setFormData(prev => ({ ...prev, budgetType: e.target.value as 'EXPENSE' | 'INCOME' | 'PROJECT' }))}
                            className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            disabled={isSubmitting}
                        >
                            <option value="EXPENSE">Uitgaven</option>
                            <option value="INCOME">Inkomsten</option>
                            <option value="PROJECT">Project</option>
                        </select>
                    </div>

                    <div>
                        <IconPicker
                            selectedIcon={formData.icon}
                            onSelect={(icon) => setFormData(prev => ({ ...prev, icon }))}
                            label="Icoon (optioneel)"
                        />
                    </div>

                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p className="text-sm text-blue-800">
                            <strong>Let op:</strong> Budgetten zijn nu eenvoudige containers voor categorieën.
                            Je hoeft geen bedragen of datums in te stellen - inzichten worden automatisch berekend
                            op basis van je daadwerkelijke uitgaven!
                        </p>
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