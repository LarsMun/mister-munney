// frontend/src/shared/components/ColorPicker.tsx

import { useState } from 'react';

interface ColorPickerProps {
    selectedColor: string;
    onSelect: (color: string) => void;
    label?: string;
}

// Palette van mooie kleuren voor categorieën (geen witte of bijna-witte kleuren)
const COLOR_PALETTE = [
    // Rood / Roze tinten
    '#FF6B6B', '#E07A5F', '#F87171', '#FB7185', '#F472B6', '#EC4899',
    // Oranje / Geel tinten
    '#FF8C42', '#FB923C', '#FDBA74', '#FBBF24', '#FCD34D', '#FACC15',
    // Groen tinten
    '#4ADE80', '#34D399', '#10B981', '#22C55E', '#84CC16', '#A3E635',
    // Blauw / Cyaan tinten
    '#38BDF8', '#0EA5E9', '#3B82F6', '#60A5FA', '#22D3EE', '#06B6D4',
    // Paars / Violet tinten
    '#A78BFA', '#8B5CF6', '#A855F7', '#C084FC', '#D946EF', '#E879F9',
    // Pastel tinten (maar niet te licht)
    '#FDA4AF', '#FDBA74', '#BEF264', '#86EFAC', '#67E8F9', '#A5B4FC',
    '#C4B5FD', '#F0ABFC', '#FCA5A1', '#FCD34D', '#6EE7B7', '#7DD3FC',
];

// Check if a color is too light (close to white)
function isColorTooLight(hex: string): boolean {
    // Remove # if present
    const color = hex.replace('#', '');
    if (color.length !== 6) return false;

    const r = parseInt(color.substring(0, 2), 16);
    const g = parseInt(color.substring(2, 4), 16);
    const b = parseInt(color.substring(4, 6), 16);

    // Calculate perceived brightness (YIQ formula)
    const brightness = (r * 299 + g * 587 + b * 114) / 1000;

    // If brightness is above 230 (out of 255), it's too light
    return brightness > 230;
}

// Get a random color from the palette
function getRandomPaletteColor(): string {
    return COLOR_PALETTE[Math.floor(Math.random() * COLOR_PALETTE.length)];
}

export function ColorPicker({ selectedColor, onSelect, label = 'Kies een kleur' }: ColorPickerProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [customColor, setCustomColor] = useState(selectedColor);
    const [colorWarning, setColorWarning] = useState<string | null>(null);

    const handleColorSelect = (color: string) => {
        setColorWarning(null);
        onSelect(color);
        setIsOpen(false);
    };

    const handleCustomColorChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const color = e.target.value;
        setCustomColor(color);

        if (isColorTooLight(color)) {
            setColorWarning('Deze kleur is te licht. Kies een donkerdere kleur.');
            // Don't apply the color, keep the old one
        } else {
            setColorWarning(null);
            onSelect(color);
        }
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
                                        if (isColorTooLight(value)) {
                                            setColorWarning('Deze kleur is te licht. Kies een donkerdere kleur.');
                                        } else {
                                            setColorWarning(null);
                                            onSelect(value);
                                        }
                                    }
                                }}
                                placeholder="#3B82F6"
                                className="flex-1 px-2 py-1 text-sm border border-gray-300 rounded font-mono focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                        </div>
                        {colorWarning && (
                            <p className="mt-1 text-xs text-amber-600">{colorWarning}</p>
                        )}
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
