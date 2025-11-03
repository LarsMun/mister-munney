// frontend/src/shared/components/ColorPicker.tsx

import { useState } from 'react';

interface ColorPickerProps {
    selectedColor: string;
    onSelect: (color: string) => void;
    label?: string;
}

// Palette van mooie kleuren voor categorieën
const COLOR_PALETTE = [
    '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF', '#FFD3B6',
    '#C1C8E4', '#CFBFF7', '#FCF5C7', '#F8C8DC', '#A8E6CF',
    '#FFD700', '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4',
    '#FFEAA7', '#DFE6E9', '#74B9FF', '#A29BFE', '#FD79A8',
    '#FDCB6E', '#6C5CE7', '#00B894', '#00CEC9', '#FF7675',
    '#FFB6C1', '#87CEEB', '#98D8C8', '#F7DC6F', '#BB8FCE',
    '#85C1E2', '#F8B88B', '#FAD02C', '#A8DADC', '#E07A5F',
];

export function ColorPicker({ selectedColor, onSelect, label = 'Kies een kleur' }: ColorPickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [customColor, setCustomColor] = useState(selectedColor);

    const handleColorSelect = (color: string) => {
        onSelect(color);
        setIsOpen(false);
    };

    const handleCustomColorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const color = e.target.value;
        setCustomColor(color);
        onSelect(color);
    };

    return (
        <div className="relative">
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
            </label>

            {/* Selected Color Display / Open Button */}
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center space-x-3">
                    <div
                        className="w-6 h-6 rounded border border-gray-300"
                        style={{ backgroundColor: selectedColor }}
                    />
                    <span className="text-sm text-gray-700 font-mono">{selectedColor}</span>
                </div>
                <span className="text-gray-400">{isOpen ? '▲' : '▼'}</span>
            </button>

            {/* Dropdown Panel */}
            {isOpen && (
                <div className="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg p-3">
                    {/* Color Grid */}
                    <div className="grid grid-cols-7 gap-2 mb-3">
                        {COLOR_PALETTE.map((color) => (
                            <button
                                key={color}
                                type="button"
                                onClick={() => handleColorSelect(color)}
                                className={`w-8 h-8 rounded border-2 transition-all hover:scale-110 ${
                                    selectedColor === color
                                        ? 'border-blue-500 ring-2 ring-blue-300'
                                        : 'border-gray-300'
                                }`}
                                style={{ backgroundColor: color }}
                                title={color}
                            />
                        ))}
                    </div>

                    {/* Custom Color Input */}
                    <div className="pt-3 border-t border-gray-200">
                        <label className="block text-xs font-medium text-gray-600 mb-1">
                            Aangepaste kleur
                        </label>
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                value={customColor}
                                onChange={handleCustomColorChange}
                                className="w-12 h-8 rounded border border-gray-300 cursor-pointer"
                            />
                            <input
                                type="text"
                                value={customColor}
                                onChange={(e) => {
                                    const value = e.target.value;
                                    setCustomColor(value);
                                    if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                                        onSelect(value);
                                    }
                                }}
                                placeholder="#FFFFFF"
                                className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>
            )}

            {/* Click outside to close */}
            {isOpen && (
                <div
                    className="fixed inset-0 z-40"
                    onClick={() => setIsOpen(false)}
                />
            )}
        </div>
    );
}
