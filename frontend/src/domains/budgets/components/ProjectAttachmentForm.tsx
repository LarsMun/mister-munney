import { useState } from 'react';
import { uploadProjectAttachment } from '../services/AdaptiveDashboardService';
import toast from 'react-hot-toast';

interface ProjectAttachmentFormProps {
    isOpen: boolean;
    onClose: () => void;
    projectId: number;
    onSuccess?: () => void;
}

export default function ProjectAttachmentForm({ isOpen, onClose, projectId, onSuccess }: ProjectAttachmentFormProps) {
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [category, setCategory] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!title.trim()) {
            toast.error('Voer een titel in');
            return;
        }

        if (!file) {
            toast.error('Selecteer een bestand');
            return;
        }

        setIsSubmitting(true);

        try {
            await uploadProjectAttachment(
                projectId,
                file,
                title.trim(),
                description.trim() || undefined,
                category || undefined
            );

            toast.success('Bestand succesvol geüpload');

            // Reset form
            setTitle('');
            setDescription('');
            setCategory('');
            setFile(null);

            if (onSuccess) {
                onSuccess();
            }

            onClose();
        } catch (error) {
            console.error('Error uploading attachment:', error);
            toast.error('Fout bij het uploaden van bestand');
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
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true" aria-labelledby="attachment-form-title">
            <div className="bg-white rounded-lg shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h2 id="attachment-form-title" className="text-xl font-semibold text-gray-800">Document Uploaden</h2>
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
                        {/* Title */}
                        <div>
                            <label htmlFor="title" className="block text-sm font-medium text-gray-700 mb-1">
                                Titel *
                            </label>
                            <input
                                type="text"
                                id="title"
                                value={title}
                                onChange={(e) => setTitle(e.target.value)}
                                placeholder="Bijv. Offerte leverancier X"
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                required
                            />
                        </div>

                        {/* Category */}
                        <div>
                            <label htmlFor="category" className="block text-sm font-medium text-gray-700 mb-1">
                                Categorie (optioneel)
                            </label>
                            <select
                                id="category"
                                value={category}
                                onChange={(e) => setCategory(e.target.value)}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                            >
                                <option value="">Geen categorie</option>
                                <option value="offer">Offerte</option>
                                <option value="contract">Contract</option>
                                <option value="invoice">Factuur</option>
                                <option value="document">Document</option>
                                <option value="other">Overig</option>
                            </select>
                        </div>

                        {/* Description */}
                        <div>
                            <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-1">
                                Beschrijving (optioneel)
                            </label>
                            <textarea
                                id="description"
                                value={description}
                                onChange={(e) => setDescription(e.target.value)}
                                rows={3}
                                placeholder="Aanvullende informatie..."
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                            />
                        </div>

                        {/* File Upload */}
                        <div>
                            <label htmlFor="file" className="block text-sm font-medium text-gray-700 mb-1">
                                Bestand *
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
                                required
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
                                        aria-label={`Verwijder bestand ${file.name}`}
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
                                {isSubmitting ? 'Bezig...' : 'Uploaden'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
