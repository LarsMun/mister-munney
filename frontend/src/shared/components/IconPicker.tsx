import React, { useState, useEffect } from 'react';
import { fetchIcons, API_URL } from '../../lib/api';

interface IconPickerProps {
    selectedIcon: string | null | undefined;
    onSelect: (icon: string | null) => void;
    label?: string;
}

export function IconPicker({ selectedIcon, onSelect, label = 'Kies een icoon' }: IconPickerProps) {
    const [icons, setIcons] = useState<string[]>([]);
    const [isOpen, setIsOpen] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const loadIcons = async () => {
            setLoading(true);
            setError(null);
            try {
                const iconList = await fetchIcons();
                setIcons(iconList);
            } catch (err) {
                setError('Kon iconen niet laden');
                console.error('Failed to load icons:', err);
            } finally {
                setLoading(false);
            }
        };

        if (isOpen && icons.length === 0) {
            loadIcons();
        }
    }, [isOpen]);

    const filteredIcons = icons.filter(icon =>
        icon.toLowerCase().includes(searchQuery.toLowerCase())
    );

    const displayedIcons = filteredIcons.slice(0, 100); // Limit to 100 for performance

    const handleClear = () => {
        onSelect(null);
        setIsOpen(false);
    };

    return (
        <div className="relative">
            <label className="block text-sm font-medium text-gray-700 mb-1">
                {label}
            </label>

            {/* Selected Icon Display / Open Button */}
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="w-full flex items-center justify-between px-3 py-2 border border-gray-300 rounded-md bg-white hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center space-x-2">
                    {selectedIcon ? (
                        <>
                            <img
                                src={`${API_URL}/api/icons/${selectedIcon}`}
                                alt=""
                                className="w-5 h-5"
                            />
                            <span className="text-sm text-gray-700">{selectedIcon}</span>
                        </>
                    ) : (
                        <span className="text-sm text-gray-500">Geen icoon geselecteerd</span>
                    )}
                </div>
                <span className="text-gray-400">{isOpen ? '▲' : '▼'}</span>
            </button>

            {/* Dropdown Panel */}
            {isOpen && (
                <div className="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg">
                    {/* Search Input */}
                    <div className="p-2 border-b border-gray-200">
                        <input
                            type="text"
                            placeholder="Zoek icoon..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="w-full px-3 py-1 text-sm border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                            autoFocus
                        />
                    </div>

                    {/* Clear Button */}
                    {selectedIcon && (
                        <div className="p-2 border-b border-gray-200">
                            <button
                                type="button"
                                onClick={handleClear}
                                className="w-full px-3 py-1 text-sm text-red-600 hover:bg-red-50 rounded transition-colors"
                            >
                                ✕ Verwijder icoon
                            </button>
                        </div>
                    )}

                    {/* Icon Grid */}
                    <div className="p-2 max-h-64 overflow-y-auto">
                        {loading && (
                            <div className="text-center py-4 text-sm text-gray-500">
                                Laden...
                            </div>
                        )}

                        {error && (
                            <div className="text-center py-4 text-sm text-red-600">
                                {error}
                            </div>
                        )}

                        {!loading && !error && displayedIcons.length === 0 && (
                            <div className="text-center py-4 text-sm text-gray-500">
                                Geen iconen gevonden
                            </div>
                        )}

                        {!loading && !error && displayedIcons.length > 0 && (
                            <>
                                <div className="grid grid-cols-8 gap-1">
                                    {displayedIcons.map((icon) => (
                                        <button
                                            key={icon}
                                            type="button"
                                            onClick={() => {
                                                onSelect(icon);
                                                setIsOpen(false);
                                            }}
                                            className={`p-2 rounded hover:bg-blue-100 transition-colors ${
                                                selectedIcon === icon ? 'bg-blue-200 ring-2 ring-blue-500' : ''
                                            }`}
                                            title={icon}
                                        >
                                            <img
                                                src={`${API_URL}/api/icons/${icon}`}
                                                alt={icon}
                                                className="w-5 h-5"
                                            />
                                        </button>
                                    ))}
                                </div>

                                {filteredIcons.length > 100 && (
                                    <div className="mt-2 text-xs text-center text-gray-500">
                                        Toon {displayedIcons.length} van {filteredIcons.length} iconen
                                        (verfijn je zoekopdracht om meer te zien)
                                    </div>
                                )}
                            </>
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
