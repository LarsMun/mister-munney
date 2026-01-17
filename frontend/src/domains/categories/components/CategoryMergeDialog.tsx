// frontend/src/domains/categories/components/CategoryMergeDialog.tsx

import { useState, useEffect } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { X, AlertTriangle, ArrowRight, CheckCircle } from 'lucide-react';
import type { Category } from '../models/Category';
import api from '../../../lib/axios';

interface MergePreview {
    sourceCategory: {
        id: number;
        name: string;
        color: string;
    };
    targetCategory: {
        id: number;
        name: string;
        color: string;
    };
    transactionsToMove: number;
    totalAmount: string;
    dateRange: {
        first: string | null;
        last: string | null;
    };
    targetCurrentCount: number;
    targetProjectedCount: number;
}

interface CategoryMergeDialogProps {
    isOpen: boolean;
    sourceCategory: Category | null;
    categories: Category[];
    accountId: number;
    onClose: () => void;
    onMerge: (sourceId: number, targetId: number) => Promise<void>;
}

type Step = 'select-target' | 'preview' | 'confirm';

export function CategoryMergeDialog({
    isOpen,
    sourceCategory,
    categories,
    accountId,
    onClose,
    onMerge
}: CategoryMergeDialogProps) {
    const [step, setStep] = useState<Step>('select-target');
    const [selectedTargetId, setSelectedTargetId] = useState<number | null>(null);
    const [preview, setPreview] = useState<MergePreview | null>(null);
    const [isLoadingPreview, setIsLoadingPreview] = useState(false);
    const [isMerging, setIsMerging] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [searchQuery, setSearchQuery] = useState('');

    // Reset state when dialog opens/closes
    useEffect(() => {
        if (isOpen) {
            setStep('select-target');
            setSelectedTargetId(null);
            setPreview(null);
            setError(null);
            setSearchQuery('');
        }
    }, [isOpen]);

    // Available target categories (exclude source category)
    const availableTargets = categories.filter(cat =>
        cat.id !== sourceCategory?.id &&
        cat.name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const selectedTarget = categories.find(cat => cat.id === selectedTargetId);

    const fetchPreview = async () => {
        if (!sourceCategory || !selectedTargetId) return;

        setIsLoadingPreview(true);
        setError(null);

        try {
            const response = await api.get(
                `/account/${accountId}/categories/${sourceCategory.id}/merge-preview/${selectedTargetId}`
            );
            setPreview(response.data);
            setStep('preview');
        } catch (err: unknown) {
            const axiosError = err as { response?: { data?: { message?: string } } };
            setError(axiosError.response?.data?.message || 'Kon preview niet laden');
            console.error('Failed to fetch merge preview:', err);
        } finally {
            setIsLoadingPreview(false);
        }
    };

    const handleSelectTarget = (targetId: number) => {
        setSelectedTargetId(targetId);
    };

    const handleContinueToPreview = () => {
        if (selectedTargetId) {
            fetchPreview();
        }
    };

    const handleConfirmMerge = async () => {
        if (!sourceCategory || !selectedTargetId) return;

        setIsMerging(true);
        try {
            await onMerge(sourceCategory.id, selectedTargetId);
            onClose();
        } catch (err) {
            console.error('Failed to merge categories:', err);
        } finally {
            setIsMerging(false);
        }
    };

    const formatAmount = (amount: string) => {
        const num = parseFloat(amount);
        return new Intl.NumberFormat('nl-NL', {
            style: 'currency',
            currency: 'EUR'
        }).format(num);
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'N/A';
        return new Date(dateString).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    return (
        <Dialog.Root open={isOpen} onOpenChange={onClose}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/50 z-50" />
                <Dialog.Content className="fixed z-50 left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl bg-white rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
                    <div className="flex justify-between items-center p-6 border-b border-gray-200 sticky top-0 bg-white">
                        <div>
                            <Dialog.Title className="text-xl font-semibold text-gray-900">
                                Categorieën samenvoegen
                            </Dialog.Title>
                            <p className="text-sm text-gray-500 mt-1">
                                {step === 'select-target' && 'Stap 1: Selecteer doelcategorie'}
                                {step === 'preview' && 'Stap 2: Controleer samenvoegdetails'}
                                {step === 'confirm' && 'Stap 3: Bevestig samenvoegen'}
                            </p>
                        </div>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600 transition-colors"
                            disabled={isMerging}
                        >
                            <X size={24} />
                        </button>
                    </div>

                    <div className="p-6">
                        {/* Source Category Display */}
                        {sourceCategory && (
                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Broncategorie (wordt verwijderd)
                                </label>
                                <div className="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-lg">
                                    <div
                                        className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                        style={{ backgroundColor: sourceCategory.color }}
                                    >
                                        {sourceCategory.icon ? (
                                            <img
                                                src={sourceCategory.icon}
                                                alt=""
                                                className="w-5 h-5"
                                                style={{ filter: 'brightness(0) invert(1)' }}
                                            />
                                        ) : (
                                            <span className="text-xl">📁</span>
                                        )}
                                    </div>
                                    <div className="flex-1">
                                        <div className="font-medium text-gray-900">{sourceCategory.name}</div>
                                        <div className="text-sm text-gray-500">Alle transacties worden verplaatst</div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Step 1: Select Target */}
                        {step === 'select-target' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Doelcategorie (ontvangt transacties)
                                </label>

                                {/* Search */}
                                <div className="mb-3">
                                    <input
                                        type="text"
                                        placeholder="Zoek categorie..."
                                        value={searchQuery}
                                        onChange={(e) => setSearchQuery(e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    />
                                </div>

                                {/* Category List */}
                                <div className="space-y-2 max-h-96 overflow-y-auto">
                                    {availableTargets.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            Geen categorieën gevonden
                                        </div>
                                    ) : (
                                        availableTargets.map((category) => (
                                            <button
                                                key={category.id}
                                                type="button"
                                                onClick={() => handleSelectTarget(category.id)}
                                                className={`w-full flex items-center gap-3 p-3 rounded-lg border-2 transition-all ${
                                                    selectedTargetId === category.id
                                                        ? 'border-blue-500 bg-blue-50'
                                                        : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'
                                                }`}
                                            >
                                                <div
                                                    className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                                    style={{ backgroundColor: category.color }}
                                                >
                                                    {category.icon ? (
                                                        <img
                                                            src={category.icon}
                                                            alt=""
                                                            className="w-5 h-5"
                                                            style={{ filter: 'brightness(0) invert(1)' }}
                                                        />
                                                    ) : (
                                                        <span className="text-xl">📁</span>
                                                    )}
                                                </div>
                                                <div className="flex-1 text-left">
                                                    <div className="font-medium text-gray-900">{category.name}</div>
                                                </div>
                                                {selectedTargetId === category.id && (
                                                    <CheckCircle className="w-5 h-5 text-blue-600" />
                                                )}
                                            </button>
                                        ))
                                    )}
                                </div>

                                {error && (
                                    <div className="mt-4 bg-red-50 border border-red-200 rounded-lg p-3 text-red-700 text-sm">
                                        {error}
                                    </div>
                                )}

                                <div className="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors"
                                    >
                                        Annuleren
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleContinueToPreview}
                                        disabled={!selectedTargetId || isLoadingPreview}
                                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                                    >
                                        {isLoadingPreview ? (
                                            <>
                                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                                                Laden...
                                            </>
                                        ) : (
                                            <>
                                                Volgende
                                                <ArrowRight className="w-4 h-4" />
                                            </>
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}

                        {/* Step 2: Preview */}
                        {step === 'preview' && preview && selectedTarget && (
                            <div>
                                {/* Target Category */}
                                <div className="mb-6">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Doelcategorie (ontvangt transacties)
                                    </label>
                                    <div className="flex items-center gap-3 p-4 bg-green-50 border border-green-200 rounded-lg">
                                        <div
                                            className="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                            style={{ backgroundColor: selectedTarget.color }}
                                        >
                                            {selectedTarget.icon ? (
                                                <img
                                                    src={selectedTarget.icon}
                                                    alt=""
                                                    className="w-5 h-5"
                                                    style={{ filter: 'brightness(0) invert(1)' }}
                                                />
                                            ) : (
                                                <span className="text-xl">📁</span>
                                            )}
                                        </div>
                                        <div className="flex-1">
                                            <div className="font-medium text-gray-900">{selectedTarget.name}</div>
                                            <div className="text-sm text-gray-500">
                                                Huidige transacties: {preview.targetCurrentCount}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Preview Details */}
                                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                    <div className="flex items-start gap-3 mb-4">
                                        <AlertTriangle className="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" />
                                        <div>
                                            <h4 className="font-medium text-blue-900 mb-2">Preview van wijzigingen</h4>
                                            <div className="space-y-2 text-sm text-blue-800">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">•</span>
                                                    <span><strong>{preview.transactionsToMove}</strong> transactie{preview.transactionsToMove !== 1 ? 's' : ''} worden verplaatst</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">•</span>
                                                    <span>Totaalbedrag: <strong>{formatAmount(preview.totalAmount)}</strong></span>
                                                </div>
                                                {preview.dateRange.first && preview.dateRange.last && (
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-medium">•</span>
                                                        <span>Periode: {formatDate(preview.dateRange.first)} t/m {formatDate(preview.dateRange.last)}</span>
                                                    </div>
                                                )}
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">•</span>
                                                    <span>Broncategorie wordt verwijderd</span>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">•</span>
                                                    <span>
                                                        Doelcategorie krijgt <strong>{preview.targetProjectedCount}</strong> transacties
                                                        ({preview.targetCurrentCount} + {preview.transactionsToMove})
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* Warning */}
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                                    <div className="flex gap-3">
                                        <AlertTriangle className="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                                        <div>
                                            <h4 className="font-medium text-yellow-900 mb-1">Let op</h4>
                                            <p className="text-sm text-yellow-800">
                                                Deze actie kan <strong>niet</strong> ongedaan worden gemaakt.
                                                Controleer de details zorgvuldig voordat je doorgaat.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={() => setStep('select-target')}
                                        disabled={isMerging}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50"
                                    >
                                        Terug
                                    </button>
                                    <button
                                        type="button"
                                        onClick={handleConfirmMerge}
                                        disabled={isMerging}
                                        className="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        {isMerging ? 'Samenvoegen...' : 'Categorieën samenvoegen'}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
