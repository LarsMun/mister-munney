// src/domains/transactions/components/SummaryBar.tsx
import { useState } from 'react';
import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { motion, AnimatePresence } from 'framer-motion';
import {SummaryType} from "../models/SummaryType.ts";

interface Props {
    summary: SummaryType;
    startDate: string;
    endDate: string;
    handleFileUpload: (file: File) => void;
}

const formatPeriod = (startDate: string, endDate: string): string => {
    const start = new Date(startDate);
    const end = new Date(endDate);

    const monthNames = [
        'Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
        'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'
    ];

    // Check if same month and year
    if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
        return `${monthNames[start.getMonth()]} ${start.getFullYear()}`;
    }

    // Different months or years - show range
    const startDay = start.getDate();
    const endDay = end.getDate();
    const startMonth = monthNames[start.getMonth()];
    const endMonth = monthNames[end.getMonth()];
    const startYear = start.getFullYear();
    const endYear = end.getFullYear();

    if (startYear === endYear) {
        if (start.getMonth() === end.getMonth()) {
            return `${startDay}-${endDay} ${startMonth} ${startYear}`;
        }
        return `${startDay} ${startMonth} - ${endDay} ${endMonth} ${startYear}`;
    }

    return `${startDay} ${startMonth} ${startYear} - ${endDay} ${endMonth} ${endYear}`;
};

export default function SummaryBar({ summary, startDate, endDate, handleFileUpload}: Props) {
    const [showUpload, setShowUpload] = useState(false);

    return (
        <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center text-sm text-gray-700 gap-2 sm:gap-4 bg-white border border-gray-200 rounded-t-lg px-4 py-2">
            <div className="text-2xl font-bold text-gray-900">
                {formatPeriod(startDate, endDate)}
            </div>

            <AnimatePresence mode="wait">
                <motion.div
                    initial={{opacity: 0, y: -8}}
                    animate={{opacity: 1, y: 0}}
                    exit={{opacity: 0, y: -8}}
                    transition={{duration: 0.3}}
                    className="flex flex-wrap justify-end gap-6 w-full sm:w-auto"
                >
                    <div className="text-gray-400 italic">{summary.total} transacties</div>
                    <div className="flex items-center gap-1 text-red-600">
                        <span className="text-xs">▼</span>
                        <span>{formatMoney(summary.total_debit)}</span>
                    </div>
                    <div className="flex items-center gap-1 text-green-700">
                        <span className="text-xs">▲</span>
                        <span>{formatMoney(summary.total_credit)}</span>
                    </div>
                    <div
                        className={`flex items-center gap-1 ${
                            Number(summary.net_total) >= 0 ? 'text-green-800' : 'text-red-800'
                        }`}
                    >
                        <span className="text-xs">{Number(summary.net_total) > 0 ? '📈' : Number(summary.net_total) < 0 ? '📉' : '🔄'}</span>
                        <span>
                            {Number(summary.net_total) >= 0 ? '+' : ''}
                            {formatMoney(summary.net_total)}
                        </span>
                    </div>
                    <div className="text-gray-600">💰 Start: {formatMoney(summary.start_balance)}</div>
                    <div className="text-gray-600">🧾 Eind: {formatMoney(summary.end_balance)}</div>
                </motion.div>
            </AnimatePresence>

            {showUpload && (
                <div className="fixed inset-0 bg-black/30 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg shadow-lg p-6 w-full max-w-sm">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-lg font-semibold">CSV uploaden</h2>
                            <button onClick={() => setShowUpload(false)} className="text-gray-500 hover:text-black">
                                ✕
                            </button>
                        </div>
                        <input
                            type="file"
                            accept=".csv"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    handleFileUpload(file);
                                    setShowUpload(false);
                                }
                            }}
                            className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}