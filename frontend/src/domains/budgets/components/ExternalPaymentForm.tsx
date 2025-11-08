import { useState, useEffect } from 'react';
import { createExternalPayment, updateExternalPayment, uploadExternalPaymentAttachment } from '../services/AdaptiveDashboardService';
import type { CreateExternalPaymentDTO, UpdateExternalPaymentDTO, PayerSource, ExternalPayment } from '../models/AdaptiveBudget';
import toast from 'react-hot-toast';

interface ExternalPaymentFormProps {
    isOpen: boolean;
    onClose: () => void;
    budgetId: number;
    payment?: ExternalPayment; // If provided, edit mode
    onSuccess?: () => void;
}

export default function ExternalPaymentForm({ isOpen, onClose, budgetId, payment, onSuccess }: ExternalPaymentFormProps) {
    const isEditMode = !!payment;

    const [amount, setAmount] = useState('');
    const [paidOn, setPaidOn] = useState(new Date().toISOString().split('T')[0]);
    const [payerSource, setPayerSource] = useState<PayerSource>('SELF');
    const [note, setNote] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Initialize form with payment data in edit mode
    useEffect(() => {
        if (isOpen && payment) {
            // Edit mode - pre-fill with existing payment data
            // Convert amount to string and clean it (remove €, spaces, commas)
            const amountString = String(payment.amount);
            const amountValue = amountString.replace(/[€\s]/g, '').replace(/,/g, '.');
            setAmount(amountValue);
            setPaidOn(payment.paidOn);
            setPayerSource(payment.payerSource);
            setNote(payment.note);
            setFile(null);
        } else if (isOpen && !payment) {
            // Create mode - reset to defaults
            setAmount('');
            setPaidOn(new Date().toISOString().split('T')[0]);
            setPayerSource('SELF');
            setNote('');
            setFile(null);
        }
    }, [payment, isOpen]);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!amount || parseFloat(amount) <= 0) {
            toast.error('Voer een geldig bedrag in');
            return;
        }

        if (!paidOn) {
            toast.error('Selecteer een datum');
            return;
        }

        if (!note.trim()) {
            toast.error('Voer een notitie in');
            return;
        }

        setIsSubmitting(true);

        try {
            if (isEditMode && payment) {
                // Update existing payment
                const updateDto: UpdateExternalPaymentDTO = {
                    amount: parseFloat(amount),
                    paidOn,
                    payerSource,
                    note: note.trim(),
                };

                const updatedPayment = await updateExternalPayment(payment.id, updateDto);

                // Upload new file if provided
                if (file) {
                    try {
                        await uploadExternalPaymentAttachment(updatedPayment.id, file);
                        toast.success('Externe betaling bijgewerkt met nieuwe bijlage');
                    } catch (fileError) {
                        console.error('Error uploading file:', fileError);
                        toast.success('Externe betaling bijgewerkt, maar bijlage uploaden mislukt');
                    }
                } else {
                    toast.success('Externe betaling bijgewerkt');
                }
            } else {
                // Create new payment
                const createDto: CreateExternalPaymentDTO = {
                    amount: parseFloat(amount),
                    paidOn,
                    payerSource,
                    note: note.trim(),
                };

                const newPayment = await createExternalPayment(budgetId, createDto);

                // Upload file if provided
                if (file) {
                    try {
                        await uploadExternalPaymentAttachment(newPayment.id, file);
                        toast.success('Externe betaling aangemaakt met bijlage');
                    } catch (fileError) {
                        console.error('Error uploading file:', fileError);
                        toast.success('Externe betaling aangemaakt, maar bijlage uploaden mislukt');
                    }
                } else {
                    toast.success('Externe betaling aangemaakt');
                }
            }

            if (onSuccess) {
                onSuccess();
            }

            onClose();
        } catch (error) {
            console.error('Error saving external payment:', error);
            toast.error(isEditMode ? 'Fout bij het bijwerken van externe betaling' : 'Fout bij het aanmaken van externe betaling');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = e.target.files?.[0];
        if (selectedFile) {
            // Validate file type
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            if (!allowedTypes.includes(selectedFile.type)) {
                toast.error('Alleen PDF, JPG en PNG bestanden zijn toegestaan');
                return;
            }

            // Validate file size (10MB max)
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (selectedFile.size > maxSize) {
                toast.error('Bestand mag maximaal 10MB zijn');
                return;
            }

            setFile(selectedFile);
        }
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true" aria-labelledby="external-payment-title">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h2 id="external-payment-title" className="text-xl font-semibold text-gray-800">
                            {isEditMode ? 'Externe Betaling Bewerken' : 'Externe Betaling Toevoegen'}
                        </h2>
                        <button
                            onClick={onClose}
                            disabled={isSubmitting}
                            className="text-gray-500 hover:text-gray-700 text-2xl leading-none disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                            aria-label="Sluit venster"
                        >
                            ×
                        </button>
                    </div>

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="space-y-4">
                        {/* Amount */}
                        <div>
                            <label htmlFor="amount" className="block text-sm font-medium text-gray-700 mb-1">
                                Bedrag *
                            </label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500">€</span>
                                <input
                                    type="number"
                                    id="amount"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    step="0.01"
                                    min="0"
                                    placeholder="0.00"
                                    className="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    disabled={isSubmitting}
                                    required
                                />
                            </div>
                        </div>

                        {/* Paid On */}
                        <div>
                            <label htmlFor="paidOn" className="block text-sm font-medium text-gray-700 mb-1">
                                Betaald op *
                            </label>
                            <input
                                type="date"
                                id="paidOn"
                                value={paidOn}
                                onChange={(e) => setPaidOn(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                required
                            />
                        </div>

                        {/* Payer Source */}
                        <div>
                            <label htmlFor="payerSource" className="block text-sm font-medium text-gray-700 mb-1">
                                Betaler *
                            </label>
                            <select
                                id="payerSource"
                                value={payerSource}
                                onChange={(e) => setPayerSource(e.target.value as PayerSource)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                required
                            >
                                <option value="SELF">Zelf</option>
                                <option value="MORTGAGE_DEPOT">Hypotheekdepot</option>
                                <option value="INSURER">Verzekeraar</option>
                                <option value="OTHER">Overig</option>
                            </select>
                        </div>

                        {/* Note */}
                        <div>
                            <label htmlFor="note" className="block text-sm font-medium text-gray-700 mb-1">
                                Omschrijving *
                            </label>
                            <input
                                type="text"
                                id="note"
                                value={note}
                                onChange={(e) => setNote(e.target.value)}
                                placeholder="Beschrijving van de betaling..."
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                required
                            />
                        </div>

                        {/* File Upload */}
                        <div>
                            <label htmlFor="file" className="block text-sm font-medium text-gray-700 mb-1">
                                Bijlage (optioneel)
                            </label>
                            <input
                                type="file"
                                id="file"
                                onChange={handleFileChange}
                                accept=".pdf,.jpg,.jpeg,.png"
                                className="block w-full text-sm text-gray-700
                                    file:mr-4 file:py-2 file:px-4
                                    file:rounded-lg file:border-0
                                    file:text-sm file:font-semibold
                                    file:bg-blue-50 file:text-blue-700
                                    hover:file:bg-blue-100
                                    cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed
                                    border border-gray-300 rounded-lg"
                                disabled={isSubmitting}
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                PDF, JPG of PNG, max 10MB
                            </p>
                            {file && (
                                <div className="mt-2 flex items-center gap-2 text-sm text-gray-700">
                                    <span aria-hidden="true">📎</span>
                                    <span>{file.name}</span>
                                    <button
                                        type="button"
                                        onClick={() => setFile(null)}
                                        className="text-red-600 hover:text-red-700 ml-auto focus:outline-none focus:ring-2 focus:ring-red-500 rounded px-2 py-1"
                                        disabled={isSubmitting}
                                        aria-label={`Verwijder bijlage ${file.name}`}
                                    >
                                        Verwijderen
                                    </button>
                                </div>
                            )}
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 pt-4">
                            <button
                                type="button"
                                onClick={onClose}
                                disabled={isSubmitting}
                                className="flex-1 px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50"
                            >
                                Annuleren
                            </button>
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isSubmitting ? 'Bezig...' : (isEditMode ? 'Bijwerken' : 'Opslaan')}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
