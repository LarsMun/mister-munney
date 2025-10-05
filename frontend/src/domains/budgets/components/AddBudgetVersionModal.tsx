// frontend/src/domains/budgets/components/AddBudgetVersionModal.tsx

import { useState } from 'react';
import type { CreateBudgetVersion } from '../models/Budget';
import { MonthPicker } from '../../../shared/components/MonthPicker';

interface AddBudgetVersionModalProps {
    isOpen: boolean;
    onClose: () => void;
    onSubmit: (version: CreateBudgetVersion) => Promise<void>;
    currentMonth?: string; // Huidige effectiveFromMonth als suggestie
}

export function AddBudgetVersionModal({
                                          isOpen,
                                          onClose,
                                          onSubmit
                                      }: AddBudgetVersionModalProps) {
    const [monthlyAmount, setMonthlyAmount] = useState<string>('');
    const [effectiveFromMonth, setEffectiveFromMonth] = useState<string>('');
    const [effectiveUntilMonth, setEffectiveUntilMonth] = useState<string>('');
    const [changeReason, setChangeReason] = useState<string>('');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Reset form when modal opens
    const resetForm = () => {
        setMonthlyAmount('');
        setEffectiveFromMonth(getNextMonth());
        setEffectiveUntilMonth('');
        setChangeReason('');
        setError(null);
    };

    const getNextMonth = (): string => {
        const next = new Date();
        next.setMonth(next.getMonth() + 1);
        return next.toISOString().substring(0, 7);
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError(null);

        // Validation
        if (!monthlyAmount || parseFloat(monthlyAmount) <= 0) {
            setError('Voer een geldig bedrag in');
            return;
        }

        if (!effectiveFromMonth) {
            setError('Selecteer een startdatum');
            return;
        }

        // Check if effectiveUntilMonth is after effectiveFromMonth
        if (effectiveUntilMonth && effectiveUntilMonth <= effectiveFromMonth) {
            setError('Einddatum moet na de startdatum liggen');
            return;
        }

        setIsSubmitting(true);
        try {
            await onSubmit({
                monthlyAmount: parseFloat(monthlyAmount),
                effectiveFromMonth,
                effectiveUntilMonth: effectiveUntilMonth || undefined,
                changeReason: changeReason || undefined
            });
            resetForm();
            onClose();
        } catch (err: any) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Er is een fout opgetreden');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        if (!isSubmitting) {
            resetForm();
            onClose();
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h2 className="text-2xl font-bold text-gray-900">
                            Nieuwe Budgetversie
                        </h2>
                        <button
                            onClick={handleClose}
                            disabled={isSubmitting}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>

                    {/* Error Message */}
                    {error && (
                        <div className="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                            {error}
                        </div>
                    )}

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {/* Monthly Amount */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Maandelijks Bedrag *
                            </label>
                            <div className="relative">
                                <span className="absolute left-3 top-2.5 text-gray-500">€</span>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={monthlyAmount}
                                    onChange={(e) => setMonthlyAmount(e.target.value)}
                                    className="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="0.00"
                                    required
                                    autoFocus
                                />
                            </div>
                        </div>

                        {/* Effective From Month */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Geldig Vanaf *
                            </label>
                            <MonthPicker
                                value={effectiveFromMonth}
                                onChange={setEffectiveFromMonth}
                                placeholder="Kies startmaand"
                            />
                            <p className="text-xs text-gray-500 mt-2">
                                De backend sluit automatisch open-ended versies af indien nodig
                            </p>
                        </div>

                        {/* Effective Until Month */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Geldig Tot (optioneel)
                            </label>
                            <MonthPicker
                                value={effectiveUntilMonth}
                                onChange={setEffectiveUntilMonth}
                                allowEmpty={true}
                                placeholder="Geen einde"
                            />
                            <p className="text-xs text-gray-500 mt-2">
                                Laat leeg voor een open-ended versie
                            </p>
                        </div>

                        {/* Change Reason */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Reden voor Wijziging (optioneel)
                            </label>
                            <textarea
                                value={changeReason}
                                onChange={(e) => setChangeReason(e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                rows={3}
                                placeholder="Bijv. Salaris verhoging, budget aanpassing..."
                            />
                        </div>

                        {/* Actions */}
                        <div className="flex justify-end space-x-3 pt-4">
                            <button
                                type="button"
                                onClick={handleClose}
                                disabled={isSubmitting}
                                className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50"
                            >
                                Annuleren
                            </button>
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                            >
                                {isSubmitting ? 'Bezig...' : 'Versie Toevoegen'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}