import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { fetchProjectDetails, fetchProjectEntries, fetchProjectExternalPayments, fetchProjectAttachments, deleteProjectAttachment, deleteExternalPayment, removeExternalPaymentAttachment, type ProjectAttachment } from './services/AdaptiveDashboardService';
import type { ProjectDetails, ExternalPayment } from './models/AdaptiveBudget';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer, LineChart, Line } from 'recharts';
import ExternalPaymentForm from './components/ExternalPaymentForm';
import ProjectAttachmentForm from './components/ProjectAttachmentForm';
import EditProjectForm from './components/EditProjectForm';
import { BASE_URL } from '../../lib/api';
import toast from 'react-hot-toast';
import { useConfirmDialog } from '../../shared/hooks/useConfirmDialog';
import { formatMoney } from '../../shared/utils/MoneyFormat';

type TabType = 'overview' | 'entries' | 'files';

interface ProjectEntry {
    id: number;
    type: 'transaction' | 'external_payment';
    attachmentUrl?: string;
    amount: string | number;
    date: string;
    description: string;
    category?: string | { name: string };
    payerSource?: string;
    transactionType?: 'DEBIT' | 'CREDIT';
}

// Helper functions to generate download URLs for attachments
const getAttachmentDownloadUrl = (entry: ProjectEntry): string => {
    if (entry.type === 'external_payment' && entry.id) {
        return `${BASE_URL}/api/external-payments/${entry.id}/download`;
    }
    // Fallback to old URL format (shouldn't happen, but just in case)
    return entry.attachmentUrl ? `${BASE_URL}${entry.attachmentUrl}` : '';
};

const getExternalPaymentAttachmentUrl = (paymentId: number): string => {
    return `${BASE_URL}/api/external-payments/${paymentId}/download`;
};

const getProjectAttachmentUrl = (attachmentId: number, download: boolean = false): string => {
    const url = `${BASE_URL}/api/project-attachments/${attachmentId}/download`;
    return download ? `${url}?download=1` : url;
};

export default function ProjectDetailPage() {
    const { projectId } = useParams<{ projectId: string }>();
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState<TabType>('overview');
    const [project, setProject] = useState<ProjectDetails | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [isEditFormOpen, setIsEditFormOpen] = useState(false);

    useEffect(() => {
        if (projectId) {
            loadProjectDetails(parseInt(projectId));
        }
    }, [projectId]);

    const loadProjectDetails = async (id: number) => {
        setIsLoading(true);
        setError(null);
        try {
            const data = await fetchProjectDetails(id);
            setProject(data);
        } catch (err) {
            console.error('Error loading project details:', err);
            setError('Fout bij het laden van project details');
        } finally {
            setIsLoading(false);
        }
    };

    const handleEditSuccess = () => {
        if (projectId) {
            loadProjectDetails(parseInt(projectId));
        }
    };

    // Helper to format project money (backend sends euro amounts)
    const formatProjectMoney = (amount: string | number): string => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        return formatMoney(numAmount);
    };

    const getStatusStyle = () => {
        if (!project) return '';
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

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-96">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Project wordt geladen...</p>
                </div>
            </div>
        );
    }

    if (error || !project) {
        return (
            <div className="flex items-center justify-center min-h-96">
                <div className="text-center">
                    <p className="text-red-600 text-lg mb-4">{error || 'Project niet gevonden'}</p>
                    <button
                        onClick={() => navigate('/budgets')}
                        className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        Terug naar budgetten
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 p-6">
            {/* Header */}
            <div className="bg-white rounded-lg shadow mb-6 p-6">
                <div className="flex items-start justify-between mb-4">
                    <div className="flex-1">
                        <button
                            onClick={() => navigate(-1)}
                            className="text-blue-600 hover:text-blue-700 text-sm mb-2 flex items-center gap-1"
                        >
                            ← Terug
                        </button>
                        <h1 className="text-2xl font-bold text-gray-900 mb-2">{project.name}</h1>
                        {project.description && (
                            <p className="text-gray-600">{project.description}</p>
                        )}
                    </div>
                    <div className="flex items-center gap-3">
                        <button
                            onClick={() => setIsEditFormOpen(true)}
                            className="text-sm px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                        >
                            ✏️ Bewerken
                        </button>
                        <span className={`text-sm px-3 py-1.5 rounded border font-medium ${getStatusStyle()}`}>
                            {project.status === 'ACTIVE' ? 'Actief' :
                             project.status === 'COMPLETED' ? 'Afgerond' :
                             'Gearchiveerd'}
                        </span>
                    </div>
                </div>

                {/* Quick Stats */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
                    <div className="bg-red-50 rounded-lg p-4">
                        <p className="text-sm text-red-600 font-medium mb-1">Getrackte uitgaven (DEBIT)</p>
                        <p className="text-2xl font-bold text-red-900">{formatProjectMoney(project.totals.trackedDebit)}</p>
                    </div>
                    <div className="bg-green-50 rounded-lg p-4">
                        <p className="text-sm text-green-600 font-medium mb-1">Getrackte inkomsten (CREDIT)</p>
                        <p className="text-2xl font-bold text-green-900">{formatProjectMoney(project.totals.trackedCredit)}</p>
                    </div>
                    <div className="bg-blue-50 rounded-lg p-4">
                        <p className="text-sm text-blue-600 font-medium mb-1">Netto getrackt</p>
                        <p className="text-2xl font-bold text-blue-900">{formatProjectMoney(project.totals.tracked)}</p>
                    </div>
                    <div className="bg-purple-50 rounded-lg p-4">
                        <p className="text-sm text-purple-600 font-medium mb-1">Externe betalingen</p>
                        <p className="text-2xl font-bold text-purple-900">{formatProjectMoney(project.totals.external)}</p>
                    </div>
                    <div className="bg-gray-50 rounded-lg p-4 border-2 border-gray-300">
                        <p className="text-sm text-gray-600 font-medium mb-1">Totaal</p>
                        <p className="text-2xl font-bold text-gray-900">{formatProjectMoney(project.totals.total)}</p>
                    </div>
                </div>
            </div>

            {/* Tabs */}
            <div className="bg-white rounded-lg shadow mb-6">
                <div className="bg-gradient-to-r from-blue-50 to-indigo-50 border-b-2 border-blue-200">
                    <div className="flex gap-2 px-4 py-2">
                        <button
                            onClick={() => setActiveTab('overview')}
                            className={`px-6 py-3 font-semibold text-base rounded-t-lg transition-all relative ${
                                activeTab === 'overview'
                                    ? 'bg-white text-blue-700 shadow-md border-b-4 border-blue-600'
                                    : 'text-gray-700 hover:bg-white/50 hover:text-blue-600'
                            }`}
                        >
                            📊 Overzicht
                        </button>
                        <button
                            onClick={() => setActiveTab('entries')}
                            className={`px-6 py-3 font-semibold text-base rounded-t-lg transition-all relative ${
                                activeTab === 'entries'
                                    ? 'bg-white text-blue-700 shadow-md border-b-4 border-blue-600'
                                    : 'text-gray-700 hover:bg-white/50 hover:text-blue-600'
                            }`}
                        >
                            💰 Inkomsten & Uitgaven
                        </button>
                        <button
                            onClick={() => setActiveTab('files')}
                            className={`px-6 py-3 font-semibold text-base rounded-t-lg transition-all relative ${
                                activeTab === 'files'
                                    ? 'bg-white text-blue-700 shadow-md border-b-4 border-blue-600'
                                    : 'text-gray-700 hover:bg-white/50 hover:text-blue-600'
                            }`}
                        >
                            📁 Bestanden
                        </button>
                    </div>
                </div>

                {/* Tab Content */}
                <div className="p-6">
                    {activeTab === 'overview' && <OverviewTab project={project} />}
                    {activeTab === 'entries' && <EntriesTab project={project} />}
                    {activeTab === 'files' && <FilesTab project={project} />}
                </div>
            </div>

            {/* Edit Project Form Modal */}
            <EditProjectForm
                isOpen={isEditFormOpen}
                onClose={() => setIsEditFormOpen(false)}
                project={project}
                onSuccess={handleEditSuccess}
            />
        </div>
    );
}

// Overview Tab Component
function OverviewTab({ project }: { project: ProjectDetails }) {
    // Helper to format project money (backend sends euro amounts)
    const formatProjectMoney = (amount: string | number): string => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        return formatMoney(numAmount);
    };

    return (
        <div className="space-y-6">
            {/* Time Series Chart */}
            {project.timeSeries && project.timeSeries.monthlyBars.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Maandelijkse uitgaven</h3>
                    <ResponsiveContainer width="100%" height={300}>
                        <BarChart data={project.timeSeries.monthlyBars}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" />
                            <YAxis />
                            <Tooltip />
                            <Legend />
                            <Bar dataKey="tracked" fill="#3B82F6" name="Getrackt" />
                            <Bar dataKey="external" fill="#8B5CF6" name="Extern" />
                        </BarChart>
                    </ResponsiveContainer>
                </div>
            )}

            {/* Cumulative Line Chart */}
            {project.timeSeries && project.timeSeries.cumulativeLine.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Cumulatieve uitgaven</h3>
                    <ResponsiveContainer width="100%" height={250}>
                        <LineChart data={project.timeSeries.cumulativeLine}>
                            <CartesianGrid strokeDasharray="3 3" />
                            <XAxis dataKey="month" />
                            <YAxis />
                            <Tooltip />
                            <Line type="monotone" dataKey="cumulative" stroke="#10B981" strokeWidth={2} name="Totaal" />
                        </LineChart>
                    </ResponsiveContainer>
                </div>
            )}

            {/* Category Breakdown */}
            {project.totals.categoryBreakdown && project.totals.categoryBreakdown.length > 0 && (
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Uitgaven per categorie</h3>
                    <div className="space-y-2">
                        {project.totals.categoryBreakdown.map((cat) => (
                            <div key={cat.categoryId} className="flex items-center justify-between bg-gray-50 rounded-lg p-3">
                                <span className="font-medium text-gray-900">{cat.categoryName}</span>
                                <span className="font-semibold text-gray-700">{formatProjectMoney(cat.total)}</span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// Entries Tab Component
function EntriesTab({ project }: { project: ProjectDetails }) {
    const [entries, setEntries] = useState<ProjectEntry[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [filter, setFilter] = useState<'all' | 'debit' | 'credit' | 'external_payment'>('all');
    const [isAddPaymentOpen, setIsAddPaymentOpen] = useState(false);
    const { confirm, Confirm } = useConfirmDialog();

    // Helper to format project money (backend sends euro amounts)
    const formatProjectMoney = (amount: string | number): string => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        return formatMoney(numAmount);
    };

    const loadEntries = async () => {
        setIsLoading(true);
        try {
            const data = await fetchProjectEntries(project.id);
            setEntries(data as ProjectEntry[]);
        } catch (error) {
            console.error('Error loading entries:', error);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadEntries();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const handleDeleteExternalPayment = async (paymentId: number, note: string) => {
        const result = await confirm({
            title: 'Externe betaling verwijderen?',
            description: `Weet je zeker dat je de externe betaling "${note}" wilt verwijderen? Dit verwijdert ook de bijlage. Deze actie kan niet ongedaan gemaakt worden.`
        });

        if (!result.confirmed) return;

        try {
            await deleteExternalPayment(paymentId);
            toast.success('Externe betaling verwijderd');
            loadEntries();
        } catch (error) {
            console.error('Error deleting payment:', error);
            toast.error('Fout bij het verwijderen van betaling');
        }
    };

    const handleRemoveAttachment = async (paymentId: number, note: string) => {
        const result = await confirm({
            title: 'Bijlage verwijderen?',
            description: `Weet je zeker dat je de bijlage van "${note}" wilt verwijderen?`
        });

        if (!result.confirmed) return;

        try {
            await removeExternalPaymentAttachment(paymentId);
            toast.success('Bijlage verwijderd');
            loadEntries();
        } catch (error) {
            console.error('Error removing attachment:', error);
            toast.error('Fout bij het verwijderen van bijlage');
        }
    };

    // Helper to safely parse amount
    const parseAmount = (amount: string | number): number => {
        if (typeof amount === 'number') return amount;
        if (typeof amount === 'string') {
            return parseFloat(amount.replace(',', '.'));
        }
        return 0;
    };

    const filteredEntries = entries.filter(entry => {
        if (filter === 'all') return true;
        if (filter === 'external_payment') return entry.type === 'external_payment';

        // Filter by transaction type (DEBIT/CREDIT)
        if (filter === 'debit') {
            if (entry.type !== 'transaction') return false;
            // If transactionType is not available yet, check amount (negative = DEBIT)
            if (!entry.transactionType) {
                const amount = parseAmount(entry.amount);
                return amount < 0;
            }
            return entry.transactionType === 'DEBIT';
        }

        if (filter === 'credit') {
            if (entry.type !== 'transaction') return false;
            // If transactionType is not available yet, check amount (positive = CREDIT)
            if (!entry.transactionType) {
                const amount = parseAmount(entry.amount);
                return amount > 0;
            }
            return entry.transactionType === 'CREDIT';
        }

        return false;
    });

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    };

    if (isLoading) {
        return (
            <div className="text-center py-12">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p className="text-gray-600">Entries worden geladen...</p>
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header with filter and add button */}
            <div className="flex items-center justify-between">
                <div className="flex gap-2">
                    <button
                        onClick={() => setFilter('all')}
                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'all'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        Alle ({entries.length})
                    </button>
                    <button
                        onClick={() => setFilter('debit')}
                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'debit'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        Getrackte uitgaven ({entries.filter(e => e.type === 'transaction' && e.transactionType === 'DEBIT').length})
                    </button>
                    <button
                        onClick={() => setFilter('external_payment')}
                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'external_payment'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        Externe betalingen ({entries.filter(e => e.type === 'external_payment').length})
                    </button>
                    <button
                        onClick={() => setFilter('credit')}
                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                            filter === 'credit'
                                ? 'bg-blue-600 text-white'
                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        Getrackte inkomsten ({entries.filter(e => e.type === 'transaction' && e.transactionType === 'CREDIT').length})
                    </button>
                </div>

                <button
                    onClick={() => setIsAddPaymentOpen(true)}
                    className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                >
                    + Externe Betaling
                </button>
            </div>

            {/* Entries list */}
            {filteredEntries.length === 0 ? (
                <div className="text-center py-12 text-gray-500">
                    <p className="text-lg mb-2">Geen entries gevonden</p>
                    <p className="text-sm">
                        {filter === 'debit' ? 'Er zijn nog geen getrackte uitgaven (DEBIT) gekoppeld aan dit project.' :
                         filter === 'credit' ? 'Er zijn nog geen getrackte inkomsten (CREDIT) gekoppeld aan dit project.' :
                         filter === 'external_payment' ? 'Er zijn nog geen externe betalingen toegevoegd.' :
                         'Voeg categorieën toe en categoriseer transacties, of voeg externe betalingen toe.'}
                    </p>
                </div>
            ) : (
                <div className="space-y-2">
                    {filteredEntries.map((entry, idx) => (
                        <div
                            key={`${entry.type}-${entry.id}-${idx}`}
                            className="bg-white border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div className="flex-1">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className={`text-xs px-2 py-0.5 rounded font-medium ${
                                            entry.type === 'transaction'
                                                ? 'bg-blue-100 text-blue-700'
                                                : 'bg-purple-100 text-purple-700'
                                        }`}>
                                            {entry.type === 'transaction' ? 'Transactie' : 'Externe Betaling'}
                                        </span>
                                        <span className="text-sm text-gray-600">{formatDate(entry.date)}</span>
                                        {entry.category && (
                                            <span className="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">
                                                {typeof entry.category === 'string' ? entry.category : entry.category.name}
                                            </span>
                                        )}
                                        {entry.payerSource && (
                                            <span className="text-xs bg-gray-100 text-gray-700 px-2 py-0.5 rounded">
                                                {entry.payerSource}
                                            </span>
                                        )}
                                    </div>
                                    <p className="text-gray-900 font-medium">{entry.description}</p>
                                    {entry.attachmentUrl && (
                                        <div className="mt-1 flex items-center gap-2">
                                            <a
                                                href={getAttachmentDownloadUrl(entry)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-sm text-blue-600 hover:text-blue-700 inline-flex items-center gap-1"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                📎 Bijlage bekijken
                                            </a>
                                            <button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleRemoveAttachment(entry.id, entry.description);
                                                }}
                                                className="text-xs px-2 py-0.5 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
                                                title="Bijlage verwijderen"
                                            >
                                                Verwijderen
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <div className="flex items-start gap-2">
                                    <div className="text-right">
                                        <p className="text-lg font-bold text-gray-900">{formatProjectMoney(entry.amount)}</p>
                                    </div>
                                    {entry.type === 'external_payment' && (
                                        <button
                                            onClick={() => handleDeleteExternalPayment(entry.id, entry.description)}
                                            className="text-sm px-2 py-1 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
                                            title="Verwijderen"
                                        >
                                            🗑️
                                        </button>
                                    )}
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* External Payment Form Modal */}
            <ExternalPaymentForm
                isOpen={isAddPaymentOpen}
                onClose={() => setIsAddPaymentOpen(false)}
                budgetId={project.id}
                onSuccess={() => {
                    loadEntries();
                    window.location.reload(); // Refresh to update totals
                }}
            />
            {Confirm}
        </div>
    );
}

// Files Tab Component
function FilesTab({ project }: { project: ProjectDetails }) {
    const [payments, setPayments] = useState<ExternalPayment[]>([]);
    const [attachments, setAttachments] = useState<ProjectAttachment[]>([]);
    const [isLoading, setIsLoading] = useState(true);
    const [isUploadFormOpen, setIsUploadFormOpen] = useState(false);
    const { confirm, Confirm } = useConfirmDialog();

    // Helper to format project money (backend sends euro amounts)
    const formatProjectMoney = (amount: string | number): string => {
        const numAmount = typeof amount === 'string' ? parseFloat(amount) : amount;
        return formatMoney(numAmount);
    };

    const loadFiles = async () => {
        setIsLoading(true);
        try {
            const [paymentsData, attachmentsData] = await Promise.all([
                fetchProjectExternalPayments(project.id),
                fetchProjectAttachments(project.id)
            ]);
            setPayments(paymentsData);
            setAttachments(attachmentsData);
        } catch (error) {
            console.error('Error loading files:', error);
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadFiles();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [project.id]);

    const handleDeleteAttachment = async (attachmentId: number, title: string) => {
        const result = await confirm({
            title: 'Bestand verwijderen?',
            description: `Weet je zeker dat je "${title}" wilt verwijderen? Deze actie kan niet ongedaan gemaakt worden.`
        });

        if (!result.confirmed) return;

        try {
            await deleteProjectAttachment(attachmentId);
            toast.success('Bestand verwijderd');
            loadFiles();
        } catch (error) {
            console.error('Error deleting attachment:', error);
            toast.error('Fout bij het verwijderen van bestand');
        }
    };

    const handleDeletePaymentAttachment = async (paymentId: number, note: string) => {
        const result = await confirm({
            title: 'Bijlage verwijderen?',
            description: `Weet je zeker dat je de bijlage van "${note}" wilt verwijderen?`,
            checkbox: {
                label: 'Ook externe betaling verwijderen',
                defaultChecked: false
            }
        });

        if (!result.confirmed) return;

        try {
            if (result.checkboxValue) {
                // Delete entire payment (including attachment)
                await deleteExternalPayment(paymentId);
                toast.success('Externe betaling en bijlage verwijderd');
            } else {
                // Just remove the attachment
                await removeExternalPaymentAttachment(paymentId);
                toast.success('Bijlage verwijderd');
            }
            loadFiles();
        } catch (error) {
            console.error('Error deleting attachment:', error);
            toast.error('Fout bij het verwijderen');
        }
    };

    const paymentsWithAttachments = payments.filter(p => p.attachmentUrl);

    const formatDate = (dateString: string | undefined) => {
        if (!dateString) return 'Onbekende datum';
        // Handle both 'YYYY-MM-DD' and 'YYYY-MM-DD HH:MM:SS' formats
        const datePart = dateString.split(' ')[0];
        return new Date(datePart).toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    };

    const getFileExtension = (url: string) => {
        const parts = url.split('.');
        return parts[parts.length - 1].toLowerCase();
    };

    const getFileIcon = (url: string) => {
        const ext = getFileExtension(url);
        if (ext === 'pdf') return '📄';
        if (['jpg', 'jpeg', 'png', 'gif'].includes(ext)) return '🖼️';
        return '📎';
    };

    const getCategoryLabel = (category: string | null) => {
        const labels: Record<string, string> = {
            offer: 'Offerte',
            contract: 'Contract',
            invoice: 'Factuur',
            document: 'Document',
            other: 'Overig'
        };
        return category ? labels[category] || category : null;
    };

    if (isLoading) {
        return (
            <div className="text-center py-12">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p className="text-gray-600">Bestanden worden geladen...</p>
            </div>
        );
    }

    const totalFiles = paymentsWithAttachments.length + attachments.length;

    if (totalFiles === 0) {
        return (
            <div className="text-center py-12 text-gray-500">
                <p className="text-lg mb-2">Geen bestanden gevonden</p>
                <p className="text-sm mb-4">
                    Upload documenten zoals offertes, contracten en facturen.
                </p>
                <button
                    onClick={() => setIsUploadFormOpen(true)}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                >
                    + Upload Document
                </button>
                <ProjectAttachmentForm
                    isOpen={isUploadFormOpen}
                    onClose={() => setIsUploadFormOpen(false)}
                    projectId={project.id}
                    onSuccess={loadFiles}
                />
                {Confirm}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header with upload button */}
            <div className="flex justify-between items-center">
                <p className="text-gray-700">
                    {totalFiles} {totalFiles === 1 ? 'bestand' : 'bestanden'}
                </p>
                <button
                    onClick={() => setIsUploadFormOpen(true)}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                >
                    + Upload Document
                </button>
            </div>

            {/* Files grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {/* Project Attachments */}
                {attachments.map((attachment) => (
                    <div
                        key={`attachment-${attachment.id}`}
                        className="bg-white border-2 border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-lg transition-all"
                    >
                        <div className="flex items-start gap-3">
                            <div className="text-4xl">{getFileIcon(attachment.fileUrl)}</div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-start justify-between gap-2 mb-1">
                                    <p className="font-medium text-gray-900 truncate">{attachment.title}</p>
                                    {attachment.category && (
                                        <span className="text-xs px-2 py-0.5 bg-purple-100 text-purple-700 rounded whitespace-nowrap">
                                            {getCategoryLabel(attachment.category)}
                                        </span>
                                    )}
                                </div>
                                {attachment.description && (
                                    <p className="text-xs text-gray-600 mb-2 line-clamp-2">{attachment.description}</p>
                                )}
                                <p className="text-sm text-gray-600 mb-3">{formatDate(attachment.uploadedAt)}</p>
                                <div className="flex gap-2">
                                    <a
                                        href={getProjectAttachmentUrl(attachment.id, false)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-sm px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                                    >
                                        Openen
                                    </a>
                                    <a
                                        href={getProjectAttachmentUrl(attachment.id, true)}
                                        download
                                        className="text-sm px-3 py-1.5 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
                                    >
                                        Download
                                    </a>
                                    <button
                                        onClick={() => handleDeleteAttachment(attachment.id, attachment.title)}
                                        className="text-sm px-3 py-1.5 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
                                        title="Verwijderen"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}

                {/* External Payment Attachments */}
                {paymentsWithAttachments.map((payment) => (
                    <div
                        key={`payment-${payment.id}`}
                        className="bg-white border-2 border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-lg transition-all"
                    >
                        <div className="flex items-start gap-3">
                            <div className="text-4xl">{getFileIcon(payment.attachmentUrl!)}</div>
                            <div className="flex-1 min-w-0">
                                <div className="flex items-start justify-between gap-2 mb-1">
                                    <p className="font-medium text-gray-900 truncate">{payment.note}</p>
                                    <span className="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded whitespace-nowrap">
                                        Betaling
                                    </span>
                                </div>
                                <p className="text-sm text-gray-600 mb-2">{formatDate(payment.paidOn)}</p>
                                <p className="text-lg font-bold text-gray-900 mb-3">{formatProjectMoney(payment.amount)}</p>
                                <div className="flex gap-2 flex-wrap">
                                    <a
                                        href={getExternalPaymentAttachmentUrl(payment.id)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="text-sm px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                                    >
                                        Openen
                                    </a>
                                    <a
                                        href={`${getExternalPaymentAttachmentUrl(payment.id)}?download=1`}
                                        download
                                        className="text-sm px-3 py-1.5 bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
                                    >
                                        Download
                                    </a>
                                    <button
                                        onClick={() => handleDeletePaymentAttachment(payment.id, payment.note)}
                                        className="text-sm px-3 py-1.5 bg-red-100 text-red-700 rounded hover:bg-red-200 transition-colors"
                                        title="Verwijderen"
                                    >
                                        🗑️
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <ProjectAttachmentForm
                isOpen={isUploadFormOpen}
                onClose={() => setIsUploadFormOpen(false)}
                projectId={project.id}
                onSuccess={loadFiles}
            />
            {Confirm}
        </div>
    );
}
