import { memo } from 'react';
import { ProjectDetails } from '../models/AdaptiveBudget';
import { useNavigate } from 'react-router-dom';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface ProjectCardProps {
    project: ProjectDetails;
}

const ProjectCard = memo(function ProjectCard({ project }: ProjectCardProps) {
    const navigate = useNavigate();

    // Format dates
    const formatDate = (dateString?: string) => {
        if (!dateString) return null;
        return new Date(dateString).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    // Helper to format project money (backend sends euro amounts)
    const formatProjectMoney = (amount: string | number): string => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        return formatMoney(numAmount);
    };

    // Determine status styling
    const getStatusStyle = () => {
        switch (project.status) {
            case 'ACTIVE':
                return 'bg-green-100 text-green-700 border-green-200';
            case 'COMPLETED':
                return 'bg-blue-100 text-blue-700 border-blue-200';
            case 'ARCHIVED':
                return 'bg-gray-100 text-gray-600 border-gray-200';
            default:
                return 'bg-gray-100 text-gray-600 border-gray-200';
        }
    };

    // Translate status
    const getStatusLabel = () => {
        switch (project.status) {
            case 'ACTIVE':
                return 'Actief';
            case 'COMPLETED':
                return 'Afgerond';
            case 'ARCHIVED':
                return 'Gearchiveerd';
            default:
                return project.status;
        }
    };

    const handleClick = () => {
        navigate(`/budgets/projects/${project.id}`);
    };

    const handleKeyPress = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            handleClick();
        }
    };

    return (
        <article
            className="bg-white border-2 border-gray-200 rounded-lg p-5 hover:border-blue-300 hover:shadow-lg transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            onClick={handleClick}
            onKeyPress={handleKeyPress}
            tabIndex={0}
            role="button"
            aria-label={`Bekijk project ${project.name}`}
        >
            {/* Header */}
            <div className="mb-3">
                <div className="flex items-start justify-between mb-2">
                    <h4 className="font-semibold text-gray-900 text-lg">{project.name}</h4>
                    <span className={`text-xs px-2 py-1 rounded border font-medium ${getStatusStyle()}`}>
                        {getStatusLabel()}
                    </span>
                </div>
                {project.description && (
                    <p className="text-sm text-gray-600 line-clamp-2">{project.description}</p>
                )}
            </div>

            {/* Period */}
            {(project.startDate || project.endDate) && (
                <div className="mb-4 flex items-center gap-2 text-xs text-gray-700">
                    <span aria-hidden="true">📅</span>
                    <span>
                        {formatDate(project.startDate) || '...'} - {formatDate(project.endDate) || 'Doorlopend'}
                    </span>
                </div>
            )}

            {/* Duration (Looptijd) */}
            {project.totals.duration && (
                <div className="mb-4 bg-orange-50 rounded-lg p-3 border border-orange-200">
                    <div className="flex items-center justify-between">
                        <span className="text-xs text-orange-600 font-medium">⏱️ Looptijd</span>
                        <span className="text-sm font-bold text-orange-900">
                            {project.totals.duration.days === 0
                                ? '1 betaling'
                                : project.totals.duration.months > 0
                                ? `${project.totals.duration.months} ${project.totals.duration.months === 1 ? 'maand' : 'maanden'}`
                                : `${project.totals.duration.days} ${project.totals.duration.days === 1 ? 'dag' : 'dagen'}`
                            }
                        </span>
                    </div>
                    <div className="text-xs text-orange-700 mt-1">
                        {project.totals.duration.days === 0 ? (
                            <span>{formatDate(project.totals.duration.startDate)}</span>
                        ) : (
                            <span>
                                {formatDate(project.totals.duration.startDate)} - {formatDate(project.totals.duration.endDate)}
                            </span>
                        )}
                    </div>
                </div>
            )}

            {/* Totals Split */}
            <div className="space-y-2 mb-4">
                <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-700">Getrackte uitgaven (DEBIT):</span>
                    <span className="font-semibold text-red-600">{formatProjectMoney(project.totals.trackedDebit)}</span>
                </div>
                <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-700">Getrackte inkomsten (CREDIT):</span>
                    <span className="font-semibold text-green-600">{formatProjectMoney(project.totals.trackedCredit)}</span>
                </div>
                <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-700">Netto getrackt:</span>
                    <span className="font-semibold text-gray-900">{formatProjectMoney(project.totals.tracked)}</span>
                </div>
                <div className="flex justify-between items-center text-sm">
                    <span className="text-gray-700">Externe betalingen:</span>
                    <span className="font-semibold text-gray-900">{formatProjectMoney(project.totals.external)}</span>
                </div>
                <div className="flex justify-between items-center text-sm pt-2 border-t-2 border-gray-200">
                    <span className="text-gray-800 font-medium">Totaal:</span>
                    <span className="font-bold text-gray-900 text-base">{formatProjectMoney(project.totals.total)}</span>
                </div>
            </div>

            {/* Category Breakdown Preview */}
            {project.totals.categoryBreakdown && project.totals.categoryBreakdown.length > 0 && (
                <div className="border-t border-gray-200 pt-3">
                    <p className="text-xs text-gray-600 mb-2">
                        {project.categoryCount} {project.categoryCount === 1 ? 'categorie' : 'categorieën'}
                    </p>
                    <div className="flex flex-wrap gap-1">
                        {project.totals.categoryBreakdown.slice(0, 3).map((cat) => (
                            <span
                                key={cat.categoryId}
                                className="text-xs bg-gray-100 text-gray-800 px-2 py-0.5 rounded"
                                title={`${cat.categoryName}: ${formatProjectMoney(cat.total)}`}
                            >
                                {cat.categoryName}
                            </span>
                        ))}
                        {project.totals.categoryBreakdown.length > 3 && (
                            <span className="text-xs text-gray-600 px-1">
                                +{project.totals.categoryBreakdown.length - 3} meer
                            </span>
                        )}
                    </div>
                </div>
            )}
        </article>
    );
});

export default ProjectCard;
