import { useState, useMemo } from 'react';
import { ProjectDetails } from '../models/AdaptiveBudget';
import ProjectCard from './ProjectCard';

interface ProjectsSectionProps {
    projects: ProjectDetails[];
    onCreateProject?: () => void;
}

export default function ProjectsSection({ projects, onCreateProject }: ProjectsSectionProps) {
    const [filter, setFilter] = useState<'ALL' | 'ACTIVE' | 'COMPLETED'>('ACTIVE');

    // Count by status (memoized to avoid recalculating on every render)
    const counts = useMemo(() => ({
        ALL: projects.length,
        ACTIVE: projects.filter(p => p.status === 'ACTIVE').length,
        COMPLETED: projects.filter(p => p.status === 'COMPLETED').length,
    }), [projects]);

    // Filter projects based on selected status (memoized)
    const filteredProjects = useMemo(() => {
        if (filter === 'ALL') return projects;
        return projects.filter(project => project.status === filter);
    }, [projects, filter]);

    return (
        <div className="bg-white rounded-lg shadow p-6">
            {/* Header */}
            <div className="flex items-center justify-between mb-4">
                <h3 className="text-lg font-semibold text-gray-800 flex items-center gap-2">
                    <span aria-hidden="true">📋</span>
                    <span>Projecten ({filteredProjects.length})</span>
                </h3>
                {onCreateProject && (
                    <button
                        onClick={onCreateProject}
                        className="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors text-sm font-medium"
                        aria-label="Nieuw project aanmaken"
                    >
                        + Nieuw Project
                    </button>
                )}
            </div>

            {/* Filter Tabs */}
            <div className="flex gap-2 mb-6 border-b border-gray-200" role="tablist" aria-label="Project filters">
                {(['ALL', 'ACTIVE', 'COMPLETED'] as const).map((status) => (
                    <button
                        key={status}
                        onClick={() => setFilter(status)}
                        role="tab"
                        aria-selected={filter === status}
                        aria-controls="projects-grid"
                        className={`px-4 py-2 text-sm font-medium transition-colors relative focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-t ${
                            filter === status
                                ? 'text-blue-600 border-b-2 border-blue-600'
                                : 'text-gray-700 hover:text-gray-900'
                        }`}
                    >
                        {status === 'ALL' ? 'Alle' :
                         status === 'ACTIVE' ? 'Actief' :
                         'Afgerond'}
                        <span className="ml-1.5 text-xs text-gray-600">({counts[status]})</span>
                    </button>
                ))}
            </div>

            {/* Projects Grid */}
            <div id="projects-grid" role="tabpanel">
                {filteredProjects.length === 0 ? (
                    <div className="text-center py-12 text-gray-600">
                        <p className="text-lg mb-2 font-medium">Geen projecten gevonden</p>
                        <p className="text-sm text-gray-700">
                            {filter === 'ACTIVE' ? 'Je hebt geen actieve projecten.' :
                             filter === 'COMPLETED' ? 'Je hebt geen afgeronde projecten.' :
                             'Maak je eerste project aan om te beginnen.'}
                        </p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {filteredProjects.map((project) => (
                            <ProjectCard key={project.id} project={project} />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
