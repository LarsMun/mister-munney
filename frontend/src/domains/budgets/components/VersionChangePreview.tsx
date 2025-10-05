// Nieuwe component: frontend/src/domains/budgets/components/VersionChangePreview.tsx

import React from 'react';
import type { VersionChangePreview, VersionAction } from '../services/BudgetsService';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface VersionChangePreviewProps {
    preview: VersionChangePreview;
    onConfirm: () => void;
    onCancel: () => void;
    isLoading?: boolean;
}

export function VersionChangePreviewComponent({
                                                  preview,
                                                  onConfirm,
                                                  onCancel,
                                                  isLoading = false
                                              }: VersionChangePreviewProps) {
    const hasChanges = preview.actions.length > 0;

    const getActionIcon = (action: VersionAction): string => {
        switch (action.type) {
            case 'remove': return '🗑️';
            case 'adjust-start': return '⏩';
            case 'adjust-end': return '⏪';
            case 'split': return '✂️';
            case 'create-split-part': return '➕';
            default: return '📝';
        }
    };

    const getActionColor = (action: VersionAction): string => {
        switch (action.type) {
            case 'remove': return 'text-red-600 bg-red-50 border-red-200';
            case 'adjust-start':
            case 'adjust-end': return 'text-orange-600 bg-orange-50 border-orange-200';
            case 'split':
            case 'create-split-part': return 'text-blue-600 bg-blue-50 border-blue-200';
            default: return 'text-gray-600 bg-gray-50 border-gray-200';
        }
    };

    const formatDate = (date: Date | undefined): string => {
        if (!date) return '';
        return date.toISOString().substring(0, 7);
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
                <div className="p-6">
                    {/* Header */}
                    <div className="flex justify-between items-center mb-6">
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">
                                Budget Wijzigingen Preview
                            </h2>
                            <p className="text-sm text-gray-600 mt-1">
                                Controleer de wijzigingen voordat je doorgaat
                            </p>
                        </div>
                        <button
                            onClick={onCancel}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            ✕
                        </button>
                    </div>

                    {/* Nieuwe Versie Info */}
                    <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <div className="flex items-center space-x-2 mb-2">
                            <span className="text-green-600">➕</span>
                            <h3 className="text-sm font-medium text-green-900">
                                Nieuwe Budget Versie
                            </h3>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-green-700">Bedrag:</span>
                                <span className="ml-2 font-medium">
                                    {formatMoney(preview.newVersion.monthlyAmount)}
                                </span>
                            </div>
                            <div>
                                <span className="text-green-700">Geldig vanaf:</span>
                                <span className="ml-2 font-medium">
                                    {preview.newVersion.effectiveFromMonth}
                                    {preview.newVersion.effectiveUntilMonth
                                        ? ` tot ${preview.newVersion.effectiveUntilMonth}`
                                        : ' (oneindig)'
                                    }
                                </span>
                            </div>
                        </div>
                        {preview.newVersion.changeReason && (
                            <div className="mt-2 text-sm text-green-700">
                                <span className="font-medium">Reden:</span> {preview.newVersion.changeReason}
                            </div>
                        )}
                    </div>

                    {/* Affected Versions */}
                    {hasChanges ? (
                        <div className="space-y-4 mb-6">
                            <h3 className="text-lg font-medium text-gray-900">
                                Impact op Bestaande Versies ({preview.actions.length})
                            </h3>

                            <div className="space-y-3">
                                {preview.actions.map((action, index) => (
                                    <div
                                        key={index}
                                        className={`border rounded-lg p-3 ${getActionColor(action)}`}
                                    >
                                        <div className="flex items-start space-x-3">
                                            <span className="text-lg">
                                                {getActionIcon(action)}
                                            </span>
                                            <div className="flex-1">
                                                <div className="font-medium text-sm mb-1">
                                                    {action.reason}
                                                </div>

                                                {/* Huidige versie info */}
                                                <div className="text-sm opacity-75 mb-2">
                                                    Huidige versie: {formatMoney(action.version.monthlyAmount)}
                                                    ({action.version.effectiveFromMonth}
                                                    {action.version.effectiveUntilMonth
                                                        ? ` - ${action.version.effectiveUntilMonth}`
                                                        : ' - nu'
                                                    })
                                                </div>

                                                {/* Wijzigingen */}
                                                {action.type === 'remove' && (
                                                    <div className="text-sm font-medium">
                                                        ❌ Deze versie wordt volledig verwijderd
                                                    </div>
                                                )}

                                                {action.type === 'adjust-start' && action.newStartDate && (
                                                    <div className="text-sm font-medium">
                                                        📅 Nieuwe startdatum: {formatDate(action.newStartDate)}
                                                    </div>
                                                )}

                                                {action.type === 'adjust-end' && action.newEndDate && (
                                                    <div className="text-sm font-medium">
                                                        📅 Nieuwe einddatum: {formatDate(action.newEndDate)}
                                                    </div>
                                                )}

                                                {action.type === 'split' && action.newEndDate && (
                                                    <div className="text-sm font-medium">
                                                        ✂️ Verkort tot: {action.version.effectiveFromMonth} - {formatDate(action.newEndDate)}
                                                    </div>
                                                )}

                                                {action.type === 'create-split-part' && action.newStartDate && (
                                                    <div className="text-sm font-medium">
                                                        ➕ Nieuw deel aangemaakt: {formatDate(action.newStartDate)}
                                                        {action.originalEndDate
                                                            ? ` - ${formatDate(action.originalEndDate)}`
                                                            : ' - nu'
                                                        }
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                            <div className="flex items-center space-x-2">
                                <span className="text-gray-600">ℹ️</span>
                                <span className="text-sm text-gray-700">
                                    Geen bestaande versies worden beïnvloed door deze wijziging.
                                </span>
                            </div>
                        </div>
                    )}

                    {/* Warning voor antidatering */}
                    {isAntidating(preview.newVersion.effectiveFromMonth) && (
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <div className="flex items-center space-x-2 mb-2">
                                <span className="text-yellow-600">⚠️</span>
                                <span className="text-sm font-medium text-yellow-900">
                                    Antidatering Waarschuwing
                                </span>
                            </div>
                            <p className="text-sm text-yellow-800">
                                Je gaat een versie toevoegen met een datum in het verleden.
                                Dit kan invloed hebben op historische rapportages en berekeningen.
                            </p>
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex justify-end space-x-3 pt-6 border-t">
                        <button
                            type="button"
                            onClick={onCancel}
                            className="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Annuleren
                        </button>
                        <button
                            onClick={onConfirm}
                            disabled={isLoading}
                            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center space-x-2"
                        >
                            {isLoading && (
                                <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                            )}
                            <span>{isLoading ? 'Bezig...' : 'Bevestigen'}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Helper functie
function isAntidating(dateString: string): boolean {
    const date = new Date(dateString + '-01');
    const today = new Date();
    const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    return date < currentMonth;
}