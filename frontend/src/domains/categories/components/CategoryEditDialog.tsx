// frontend/src/domains/categories/components/CategoryEditDialog.tsx

import { useState, useEffect } from 'react';
import * as Dialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { IconPicker } from '../../../shared/components/IconPicker';
import { ColorPicker } from '../../../shared/components/ColorPicker';
import type { Category } from '../models/Category';

interface CategoryEditDialogProps {
    isOpen: boolean;
    category: Category | null;
    onClose: () => void;
    onSave: (categoryId: number, updates: { name: string; color: string; icon: string | null }) => Promise<void>;
}

export function CategoryEditDialog({ isOpen, category, onClose, onSave }: CategoryEditDialogProps) {
    const [formData, setFormData] = useState({
        name: '',
        color: '#CFBFF7',
        icon: null as string | null,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Update form when category changes
    useEffect(() => {
        if (category) {
            setFormData({
                name: category.name,
                color: category.color,
                icon: category.icon || null,
            });
        }
    }, [category]);

    const validate = () => {
        const newErrors: Record<string, string> = {};

        if (!formData.name.trim()) {
            newErrors.name = 'Naam is verplicht';
        }

        if (!formData.color || !/^#[0-9A-Fa-f]{6}$/.test(formData.color)) {
            newErrors.color = 'Ongeldige kleur (moet #RRGGBB formaat zijn)';
        }

        setErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!category || !validate()) return;

        setIsSubmitting(true);
        try {
            await onSave(category.id, formData);
            handleClose();
        } catch (error) {
            console.error('Failed to update category:', error);
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        setErrors({});
        onClose();
    };

    return (
        <Dialog.Root open={isOpen} onOpenChange={handleClose}>
            <Dialog.Portal>
                <Dialog.Overlay className="fixed inset-0 bg-black/50 z-50" />
                <Dialog.Content className="fixed z-50 left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-lg shadow-xl">
                    <div className="flex justify-between items-center p-6 border-b border-gray-200">
                        <Dialog.Title className="text-xl font-semibold text-gray-900">
                            Categorie bewerken
                        </Dialog.Title>
                        <button
                            onClick={handleClose}
                            className="text-gray-400 hover:text-gray-600 transition-colors"
                            disabled={isSubmitting}
                        >
                            <X size={24} />
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="p-6 space-y-4">
                        {/* Preview */}
                        <div className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div
                                className="w-12 h-12 rounded-lg flex items-center justify-center"
                                style={{ backgroundColor: formData.color }}
                            >
                                {formData.icon ? (
                                    <img
                                        src={formData.icon}
                                        alt=""
                                        className="w-6 h-6"
                                        style={{ filter: 'brightness(0) invert(1)' }}
                                    />
                                ) : (
                                    <span className="text-2xl">📁</span>
                                )}
                            </div>
                            <div>
                                <div className="font-medium text-gray-900">
                                    {formData.name || 'Categorie naam'}
                                </div>
                                <div className="text-sm text-gray-500">Voorbeeld weergave</div>
                            </div>
                        </div>

                        {/* Name Input */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Naam *
                            </label>
                            <input
                                type="text"
                                value={formData.name}
                                onChange={(e) => setFormData(prev => ({ ...prev, name: e.target.value }))}
                                className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Bijv. Boodschappen"
                                disabled={isSubmitting}
                            />
                            {errors.name && (
                                <p className="text-red-500 text-sm mt-1">{errors.name}</p>
                            )}
                        </div>

                        {/* Color Picker */}
                        <ColorPicker
                            selectedColor={formData.color}
                            onSelect={(color) => setFormData(prev => ({ ...prev, color }))}
                            label="Kleur *"
                        />
                        {errors.color && (
                            <p className="text-red-500 text-sm mt-1">{errors.color}</p>
                        )}

                        {/* Icon Picker */}
                        <IconPicker
                            selectedIcon={formData.icon}
                            onSelect={(icon) => setFormData(prev => ({ ...prev, icon }))}
                            label="Icoon (optioneel)"
                        />

                        {/* Form Actions */}
                        <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
                            <button
                                type="button"
                                onClick={handleClose}
                                disabled={isSubmitting}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors disabled:opacity-50"
                            >
                                Annuleren
                            </button>
                            <button
                                type="submit"
                                disabled={isSubmitting}
                                className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors disabled:opacity-50"
                            >
                                {isSubmitting ? 'Opslaan...' : 'Opslaan'}
                            </button>
                        </div>
                    </form>
                </Dialog.Content>
            </Dialog.Portal>
        </Dialog.Root>
    );
}
