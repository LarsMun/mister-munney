// frontend/src/domains/categories/components/CategoryDeleteDialog.tsx

import { useState, useEffect } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { X, AlertTriangle } from 'lucide-react';
import type { Category } from '../models/Category';
import api from '../../../lib/axios';

interface DeletePreview {
    canDelete: boolean;
    transactionCount: number;
    categoryName: string;
}

interface CategoryDeleteDialogProps {
    isOpen: boolean;
    category: Category | null;
    accountId: number;
    onClose: () => void;
    onDelete: (categoryId: number) => Promise<void>;
    onMerge?: (category: Category) => void;
}

export function CategoryDeleteDialog({
    isOpen,
    category,
    accountId,
    onClose,
    onDelete,
    onMerge
}: CategoryDeleteDialogProps) {
    const [preview, setPreview] = useState<DeletePreview | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // Fetch delete preview when dialog opens
    useEffect(() => {
        const fetchDeletePreview = async () => {
            if (!category) return;

            setIsLoading(true);
            setError(null);

            try {
                const response = await api.get(
                    `/account/${accountId}/categories/${category.id}/preview-delete`
                );
                setPreview(response.data);
            } catch (err: unknown) {
                const axiosError = err as { response?: { data?: { message?: string } } };
                setError(axiosError.response?.data?.message || 'Kon preview niet laden');
                console.error('Failed to fetch delete preview:', err);
            } finally {
                setIsLoading(false);
            }
        };

        if (isOpen && category) {
            fetchDeletePreview();
        } else {
            setPreview(null);
            setError(null);
        }
    }, [isOpen, category, accountId]);

    const handleDelete = async () => {
        if (!category) return;

        setIsDeleting(true);
        try {
            await onDelete(category.id);
            onClose();
        } catch (err) {
            console.error('Failed to delete category:', err);
        } finally {
            setIsDeleting(false);
        }
    };

    const handleMergeClick = () => {
        if (category && onMerge) {
            onMerge(category);
            onClose();
        }
    };

    return (
        <Dialog.Root open={isOpen} onOpenChange={onClose}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/50 z-50" />
                <Dialog.Content className="fixed z-50 left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-lg shadow-xl">
                    <div className="flex justify-between items-center p-6 border-b border-gray-200">
                        <Dialog.Title className="text-xl font-semibold text-gray-900">
                            Categorie verwijderen
                        </Dialog.Title>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600 transition-colors"
                            disabled={isDeleting}
                        >
                            <X size={24} />
                        </button>
                    </div>

                    <div className="p-6">
                        {isLoading && (
                            <div className="flex items-center justify-center py-8">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                            </div>
                        )}

                        {error && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-red-700">
                                {error}
                            </div>
                        )}

                        {preview && !isLoading && (
                            <>
                                {/* Category Info */}
                                <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg mb-4">
                                    <div
                                        className="w-10 h-10 rounded-lg flex items-center justify-center"
                                        style={{ backgroundColor: category?.color }}
                                    >
                                        {category?.icon ? (
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
                                    <div>
                                        <div className="font-medium text-gray-900">{preview.categoryName}</div>
                                        <div className="text-sm text-gray-500">
                                            {preview.transactionCount} transactie{preview.transactionCount !== 1 ? 's' : ''}
                                        </div>
                                    </div>
                                </div>

                                {/* Cannot Delete Warning */}
                                {!preview.canDelete && (
                                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                        <div className="flex gap-3">
                                            <AlertTriangle className="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                                            <div>
                                                <h4 className="font-medium text-yellow-900 mb-1">
                                                    Kan categorie niet verwijderen
                                                </h4>
                                                <p className="text-sm text-yellow-800">
                                                    De categorie "{preview.categoryName}" heeft nog{' '}
                                                    <strong>{preview.transactionCount} gekoppelde transactie{preview.transactionCount !== 1 ? 's' : ''}</strong>.
                                                    Je moet eerst alle transacties verplaatsen voordat je deze categorie kunt verwijderen.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Can Delete Confirmation */}
                                {preview.canDelete && (
                                    <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                                        <div className="flex gap-3">
                                            <div className="text-green-600 text-2xl">✓</div>
                                            <div>
                                                <h4 className="font-medium text-green-900 mb-1">
                                                    Categorie kan worden verwijderd
                                                </h4>
                                                <p className="text-sm text-green-800">
                                                    Deze categorie heeft geen gekoppelde transacties en kan veilig worden verwijderd.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                )}

                                {/* Actions */}
                                <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                                    <button
                                        type="button"
                                        onClick={onClose}
                                        disabled={isDeleting}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50"
                                    >
                                        Annuleren
                                    </button>

                                    {!preview.canDelete && onMerge && (
                                        <button
                                            type="button"
                                            onClick={handleMergeClick}
                                            disabled={isDeleting}
                                            className="px-4 py-2 text-sm font-medium text-white bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors disabled:opacity-50"
                                        >
                                            Categorieën samenvoegen →
                                        </button>
                                    )}

                                    {preview.canDelete && (
                                        <button
                                            type="button"
                                            onClick={handleDelete}
                                            disabled={isDeleting}
                                            className="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-50"
                                        >
                                            {isDeleting ? 'Verwijderen...' : 'Verwijderen'}
                                        </button>
                                    )}
                                </div>
                            </>
                        )}
                    </div>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
