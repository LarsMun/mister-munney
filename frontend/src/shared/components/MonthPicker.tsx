// frontend/src/shared/components/MonthPicker.tsx

import React, { useState, useRef, useEffect } from 'react';

interface MonthPickerProps {
    value: string; // YYYY-MM format
    onChange: (value: string) => void;
    onBlur?: () => void;
    autoFocus?: boolean;
    allowEmpty?: boolean;
    placeholder?: string;
}

export function MonthPicker({
                                value,
                                onChange,
                                onBlur,
                                autoFocus = false,
                                allowEmpty = false,
                                placeholder = "MM-JJJJ"
                            }: MonthPickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [inputValue, setInputValue] = useState('');
    const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
    const inputRef = useRef<HTMLInputElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    const months = [
        'Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
        'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'
    ];

    // Format YYYY-MM to MM-YYYY for display
    const formatForDisplay = (val: string) => {
        if (!val || !val.match(/^\d{4}-\d{2}$/)) return val;
        const [year, month] = val.split('-');
        return `${month}-${year}`;
    };

    // Format MM-YYYY to YYYY-MM for storage
    const formatForStorage = (val: string) => {
        // Try YYYY-MM format first
        if (val.match(/^\d{4}-\d{2}$/)) return val;

        // Try MM-YYYY format
        const match = val.match(/^(\d{1,2})-(\d{4})$/);
        if (match) {
            const month = match[1].padStart(2, '0');
            const year = match[2];
            return `${year}-${month}`;
        }
        return '';
    };

    useEffect(() => {
        setInputValue(formatForDisplay(value));
        if (value && value.match(/^\d{4}-\d{2}$/)) {
            setSelectedYear(parseInt(value.split('-')[0]));
        }
    }, [value]);

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [isOpen]);

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const val = e.target.value;
        setInputValue(val);

        const formatted = formatForStorage(val);
        if (formatted) {
            onChange(formatted);
        } else if (allowEmpty && val === '') {
            onChange('');
        }
    };

    const handleMonthClick = (monthIndex: number) => {
        const month = (monthIndex + 1).toString().padStart(2, '0');
        const formatted = `${selectedYear}-${month}`;
        onChange(formatted);
        setInputValue(formatForDisplay(formatted));
        setIsOpen(false);
    };

    const handleYearChange = (delta: number) => {
        setSelectedYear(prev => prev + delta);
    };

    const handleInputClick = () => {
        setIsOpen(!isOpen);
    };

    const handleInputBlur = () => {
        if (onBlur) {
            setTimeout(() => {
                if (!containerRef.current?.contains(document.activeElement)) {
                    onBlur();
                }
            }, 100);
        }
    };

    return (
        <div className="relative inline-block" ref={containerRef}>
            <input
                ref={inputRef}
                type="text"
                value={inputValue}
                onChange={handleInputChange}
                onClick={handleInputClick}
                onBlur={handleInputBlur}
                placeholder={placeholder}
                autoFocus={autoFocus}
                className="w-28 px-3 py-1.5 text-sm border border-gray-300 rounded focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
            />

            {isOpen && (
                <div className="absolute top-full left-0 mt-1 bg-white border border-gray-300 rounded-lg shadow-lg z-50 p-3 w-64">
                    {/* Year Navigator */}
                    <div className="flex items-center justify-between mb-3 pb-2 border-b">
                        <button
                            type="button"
                            onClick={() => handleYearChange(-1)}
                            className="p-1 hover:bg-gray-100 rounded"
                        >
                            ◀
                        </button>
                        <span className="font-semibold text-gray-900">{selectedYear}</span>
                        <button
                            type="button"
                            onClick={() => handleYearChange(1)}
                            className="p-1 hover:bg-gray-100 rounded"
                        >
                            ▶
                        </button>
                    </div>

                    {/* Month Grid */}
                    <div className="grid grid-cols-3 gap-2">
                        {months.map((month, index) => {
                            const isSelected = value === `${selectedYear}-${(index + 1).toString().padStart(2, '0')}`;
                            return (
                                <button
                                    key={month}
                                    type="button"
                                    onClick={() => handleMonthClick(index)}
                                    className={`px-2 py-1.5 text-sm rounded hover:bg-blue-50 transition-colors ${
                                        isSelected
                                            ? 'bg-blue-600 text-white hover:bg-blue-700'
                                            : 'bg-gray-50 text-gray-700'
                                    }`}
                                >
                                    {month.substring(0, 3)}
                                </button>
                            );
                        })}
                    </div>

                    {allowEmpty && (
                        <button
                            type="button"
                            onClick={() => {
                                onChange('');
                                setInputValue('');
                                setIsOpen(false);
                            }}
                            className="w-full mt-2 px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded"
                        >
                            Geen einddatum
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}