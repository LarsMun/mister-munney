import { useState } from 'react';
import { updateProject } from '../services/AdaptiveDashboardService';
import type { UpdateProjectDTO, ProjectDetails } from '../models/AdaptiveBudget';
import toast from 'react-hot-toast';

interface EditProjectFormProps {
    isOpen: boolean;
    onClose: () => void;
    project: ProjectDetails;
    onSuccess?: () => void;
}

export default function EditProjectForm({ isOpen, onClose, project, onSuccess }: EditProjectFormProps) {
    const [name, setName] = useState(project.name);
    const [description, setDescription] = useState(project.description || '');
    const [durationMonths, setDurationMonths] = useState(project.durationMonths);
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        if (!name.trim()) {
            toast.error('Voer een projectnaam in');
            return;
        }

        setIsSubmitting(true);

        try {
            const dto: UpdateProjectDTO = {
                name: name.trim(),
                description: description.trim() || undefined,
                durationMonths,
            };

            await updateProject(project.id, dto);

            toast.success('Project bijgewerkt');

            if (onSuccess) {
                onSuccess();
            }

            onClose();
        } catch (error) {
            console.error('Error updating project:', error);
            toast.error('Fout bij het bijwerken van project');
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleClose = () => {
        if (isSubmitting) return;

        // Reset form on close
        setName(project.name);
        setDescription(project.description || '');
        setDurationMonths(project.durationMonths);

        onClose();
    };

    if (!isOpen) return null;

    return (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" role="dialog" aria-modal="true" aria-labelledby="project-edit-title">
            <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <h2 id="project-edit-title" className="text-xl font-semibold text-gray-800">Project Bewerken</h2>
                        <button
                            onClick={handleClose}
                            disabled={isSubmitting}
                            className="text-gray-500 hover:text-gray-700 text-2xl leading-none disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-blue-500 rounded"
                            aria-label="Sluit venster"
                        >
                            ×
                        </button>
                    </div>

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* Name */}
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                Projectnaam *
                            </label>
                            <input
                                type="text"
                                id="name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                placeholder="Bijvoorbeeld: Keuken verbouwing"
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                required
                                maxLength={255}
                            />
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
                                placeholder="Extra details over het project..."
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                maxLength={1000}
                            />
                        </div>

                        {/* Duration (Looptijd) */}
                        <div>
                            <label htmlFor="durationMonths" className="block text-sm font-medium text-gray-700 mb-1">
                                Looptijd (maanden) *
                            </label>
                            <input
                                type="number"
                                id="durationMonths"
                                value={durationMonths}
                                onChange={(e) => setDurationMonths(parseInt(e.target.value) || 2)}
                                min="1"
                                max="24"
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                disabled={isSubmitting}
                                required
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                Hoe lang dit project actief blijft na de laatste betaling
                            </p>
                        </div>

                        {/* Info Box */}
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div className="flex items-start gap-3">
                                <span className="text-blue-600 text-xl" aria-hidden="true">ℹ️</span>
                                <div className="text-sm text-blue-900">
                                    <p className="font-medium mb-1">Automatische statusbepaling:</p>
                                    <ul className="list-disc list-inside space-y-0.5 text-blue-800">
                                        <li>De status wordt automatisch berekend op basis van transactiedatums</li>
                                        <li><strong>Actief:</strong> Geen transacties of laatste betaling ≤ looptijd maanden geleden</li>
                                        <li><strong>Afgerond:</strong> Laatste betaling &gt; looptijd maanden geleden</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        {/* Actions */}
                        <div className="flex gap-3 pt-4">
                            <button
                                type="button"
                                onClick={handleClose}
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
                                {isSubmitting ? 'Bezig...' : 'Opslaan'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
