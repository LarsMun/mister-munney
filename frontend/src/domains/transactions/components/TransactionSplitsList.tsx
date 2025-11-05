import { useState, useEffect } from 'react';
import { ChevronDown, ChevronUp, Trash2, Split as SplitIcon } from 'lucide-react';
import toast from 'react-hot-toast';
import { getSplits, deleteSplits, SplitTransaction } from '../services/TransactionSplitService';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { useCategories } from '../../categories/hooks/useCategories';
import CategoryCombobox from '../../categories/components/CategoryCombobox';

interface TransactionSplitsListProps {
    accountId: number;
    transactionId: number;
    isExpanded: boolean;
    onToggle: () => void;
    onSplitsDeleted: () => void;
}

export default function TransactionSplitsList({
    accountId,
    transactionId,
    isExpanded,
    onToggle,
    onSplitsDeleted,
}: TransactionSplitsListProps) {
    const [splits, setSplits] = useState<SplitTransaction[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);

    const { categories, setCategories } = useCategories(accountId);

    useEffect(() => {
        if (isExpanded && splits.length === 0) {
            loadSplits();
        }
    }, [isExpanded]);

    const loadSplits = async () => {
        setIsLoading(true);
        try {
            const data = await getSplits(accountId, transactionId);
            setSplits(data);
        } catch (error) {
            console.error('Error loading splits:', error);
            toast.error('Fout bij het laden van splits');
        } finally {
            setIsLoading(false);
        }
    };

    const handleDeleteAll = async () => {
        if (!confirm('Weet je zeker dat je alle splits wilt verwijderen?')) {
            return;
        }

        setIsDeleting(true);
        try {
            await deleteSplits(accountId, transactionId);
            toast.success('Splits verwijderd');
            onSplitsDeleted();
        } catch (error) {
            console.error('Error deleting splits:', error);
            toast.error('Fout bij het verwijderen van splits');
        } finally {
            setIsDeleting(false);
        }
    };

    return (
        <div className="mt-2 border-l-2 border-blue-200 ml-4">
            {/* Toggle Button */}
            <button
                onClick={onToggle}
                className="flex items-center gap-2 px-3 py-1.5 text-sm text-blue-700 hover:text-blue-900 hover:bg-blue-50 rounded transition-colors"
            >
                {isExpanded ? (
                    <ChevronUp className="w-4 h-4" />
                ) : (
                    <ChevronDown className="w-4 h-4" />
                )}
                <SplitIcon className="w-4 h-4" />
                <span className="font-medium">
                    {splits.length > 0 ? `${splits.length} splits` : 'Splits'}
                </span>
            </button>

            {/* Splits List */}
            {isExpanded && (
                <div className="ml-4 mt-2 space-y-1">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-4">
                            <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                        </div>
                    ) : splits.length === 0 ? (
                        <p className="text-sm text-gray-500 py-2">Geen splits gevonden</p>
                    ) : (
                        <>
                            {splits.map((split) => (
                                <div
                                    key={split.id}
                                    className="grid grid-cols-12 gap-3 py-2 px-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                                >
                                    <div className="col-span-5 min-w-0">
                                        <div className="flex items-center gap-2">
                                            <p className="text-sm font-medium text-gray-900 truncate">
                                                {split.description}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3 mt-1">
                                            <p className="text-xs text-gray-500">
                                                {new Date(split.date).toLocaleDateString('nl-NL', {
                                                    day: '2-digit',
                                                    month: 'short',
                                                    year: 'numeric'
                                                })}
                                            </p>
                                            {split.tag && (
                                                <span className="text-xs text-gray-500">
                                                    #{split.tag}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                    <div className="col-span-2 flex items-center">
                                        <p className={`text-sm font-semibold whitespace-nowrap ${
                                            parseFloat(split.amount) < 0 ? 'text-red-600' : 'text-green-600'
                                        }`}>
                                            {formatMoney(split.amount)}
                                        </p>
                                    </div>
                                    <div className="col-span-5 flex items-center" onClick={(e) => e.stopPropagation()}>
                                        <CategoryCombobox
                                            transactionId={split.id}
                                            categoryId={split.category?.id ?? null}
                                            refresh={loadSplits}
                                            categories={categories}
                                            setCategories={setCategories}
                                            transactionType={split.transactionType}
                                        />
                                    </div>
                                </div>
                            ))}

                            {/* Delete All Button */}
                            <div className="pt-2 border-t border-gray-200 mt-2">
                                <button
                                    onClick={handleDeleteAll}
                                    disabled={isDeleting}
                                    className="flex items-center gap-2 px-3 py-1.5 text-sm text-red-600 hover:text-red-700 hover:bg-red-50 rounded transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <Trash2 className="w-4 h-4" />
                                    <span>Alle splits verwijderen</span>
                                    {isDeleting && (
                                        <div className="animate-spin rounded-full h-3 w-3 border-b-2 border-red-600"></div>
                                    )}
                                </button>
                            </div>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
