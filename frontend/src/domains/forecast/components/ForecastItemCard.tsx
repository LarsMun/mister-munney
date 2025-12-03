// frontend/src/domains/forecast/components/ForecastItemCard.tsx

import { useState } from 'react';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { API_URL } from '../../../lib/api';
import type { ForecastItem, UpdateForecastItem } from '../models/Forecast';

interface ForecastItemCardProps {
    item: ForecastItem;
    onUpdate: (itemId: number, data: UpdateForecastItem) => Promise<void>;
    onRemove: (itemId: number) => Promise<void>;
    onDragStart: (e: React.DragEvent, item: ForecastItem) => void;
    onDragEnd: () => void;
    isDragging: boolean;
}

export function ForecastItemCard({
    item,
    onUpdate,
    onRemove,
    onDragStart,
    onDragEnd,
    isDragging
}: ForecastItemCardProps) {
    const [isEditing, setIsEditing] = useState(false);
    const [editAmount, setEditAmount] = useState(item.expectedAmount.toString());
    const [isUpdating, setIsUpdating] = useState(false);

    // Voor negatieve bedragen (bijv. spaaropnamen), gebruik absoluut voor progress
    const actualForProgress = Math.abs(item.actualAmount);
    const progress = item.expectedAmount > 0
        ? Math.min((actualForProgress / item.expectedAmount) * 100, 100)
        : 0;

    const isNegativeActual = item.actualAmount < 0;
    const isComplete = !isNegativeActual && item.actualAmount >= item.expectedAmount && item.expectedAmount > 0;
    const isOverBudget = !isNegativeActual && item.actualAmount > item.expectedAmount && item.expectedAmount > 0;

    const handleSaveAmount = async () => {
        const newAmount = parseFloat(editAmount);
        if (isNaN(newAmount) || newAmount < 0) return;

        setIsUpdating(true);
        try {
            await onUpdate(item.id, { expectedAmount: newAmount });
            setIsEditing(false);
        } finally {
            setIsUpdating(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') {
            handleSaveAmount();
        } else if (e.key === 'Escape') {
            setEditAmount(item.expectedAmount.toString());
            setIsEditing(false);
        }
    };

    return (
        <div
            draggable
            onDragStart={(e) => onDragStart(e, item)}
            onDragEnd={onDragEnd}
            className={`flex items-center space-x-3 p-3 rounded-lg bg-white border transition-all cursor-grab
                ${isDragging ? 'opacity-50 border-blue-300' : 'border-gray-200 hover:border-gray-300'}
            `}
        >
            {/* Drag Handle */}
            <div className="text-gray-400 cursor-grab">⋮⋮</div>

            {/* Icon */}
            {item.icon && (
                <img
                    src={`${API_URL}/api/icons/${item.icon}`}
                    alt=""
                    className="w-6 h-6 flex-shrink-0"
                />
            )}

            {/* Name and Progress */}
            <div className="flex-1 min-w-0">
                <div className="font-medium text-gray-900 truncate">
                    {item.customName || item.name}
                </div>

                {/* Progress bar */}
                <div className="mt-1 flex items-center space-x-2">
                    <div className="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div
                            className={`h-full transition-all ${
                                isNegativeActual
                                    ? 'bg-green-500'
                                    : isOverBudget
                                        ? 'bg-red-500'
                                        : isComplete
                                            ? 'bg-green-500'
                                            : 'bg-blue-500'
                            }`}
                            style={{ width: `${Math.min(progress, 100)}%` }}
                        />
                    </div>
                    <span className="text-xs text-gray-500 w-12 text-right">
                        {progress.toFixed(0)}%
                    </span>
                </div>
            </div>

            {/* Actual Amount */}
            <div className="text-right">
                <div className={`font-semibold ${
                    isNegativeActual
                        ? 'text-green-600'
                        : isOverBudget
                            ? 'text-red-600'
                            : isComplete
                                ? 'text-green-600'
                                : 'text-gray-900'
                }`}>
                    {formatMoney(item.actualAmount)}
                </div>
                <div className="text-xs text-gray-500">
                    {isNegativeActual ? 'terugboeking' : 'actueel'}
                </div>
            </div>

            {/* Expected Amount (editable) */}
            <div className="text-right w-24">
                {isEditing ? (
                    <input
                        type="number"
                        value={editAmount}
                        onChange={(e) => setEditAmount(e.target.value)}
                        onBlur={handleSaveAmount}
                        onKeyDown={handleKeyDown}
                        disabled={isUpdating}
                        className="w-full px-2 py-1 text-sm border border-blue-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                        autoFocus
                    />
                ) : (
                    <button
                        onClick={() => setIsEditing(true)}
                        className="text-blue-600 hover:text-blue-800 font-medium hover:underline"
                        title="Klik om te bewerken"
                    >
                        {formatMoney(item.expectedAmount)}
                    </button>
                )}
                <div className="text-xs text-gray-500">verwacht</div>
            </div>

            {/* Status Icon */}
            <div className="text-lg">
                {isComplete ? (
                    <span title="Volledig">✓</span>
                ) : isOverBudget ? (
                    <span title="Over budget">⚠️</span>
                ) : (
                    <span className="text-gray-300" title="In behandeling">○</span>
                )}
            </div>

            {/* Remove Button */}
            <button
                onClick={() => onRemove(item.id)}
                className="text-gray-400 hover:text-red-600 transition-colors"
                title="Verwijderen uit forecast"
            >
                ✕
            </button>
        </div>
    );
}
