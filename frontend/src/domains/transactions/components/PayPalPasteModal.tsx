import { useState, useRef } from 'react';
import { X, FileText, CheckCircle, AlertCircle, Upload, FileSpreadsheet } from 'lucide-react';
import toast from 'react-hot-toast';
import { importPayPalTransactions, importPayPalCsv } from '../services/PayPalService';

interface PayPalPasteModalProps {
    isOpen: boolean;
    onClose: () => void;
    accountId: number;
    onSuccess: () => void;
}

type ImportMode = 'csv' | 'paste';

export default function PayPalPasteModal({
    isOpen,
    onClose,
    accountId,
    onSuccess,
}: PayPalPasteModalProps) {
    const [mode, setMode] = useState<ImportMode>('csv');
    const [pastedText, setPastedText] = useState('');
    const [selectedFile, setSelectedFile] = useState<File | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [result, setResult] = useState<{
        parsed: number;
        matched: number;
        imported: number;
        skipped: number;
    } | null>(null);
    const fileInputRef = useRef<HTMLInputElement>(null);

    if (!isOpen) return null;

    const handleImport = async () => {
        if (mode === 'csv') {
            if (!selectedFile) {
                toast.error('Selecteer eerst een CSV bestand');
                return;
            }
        } else {
            if (!pastedText.trim()) {
                toast.error('Plak eerst de transacties uit PayPal');
                return;
            }
        }

        setIsLoading(true);
        try {
            const importResult = mode === 'csv'
                ? await importPayPalCsv(accountId, selectedFile!)
                : await importPayPalTransactions(accountId, pastedText);

            setResult(importResult);

            if (importResult.imported > 0) {
                toast.success(`${importResult.imported} PayPal transacties geïmporteerd`);
                onSuccess();
            } else if (importResult.parsed === 0) {
                toast.error('Geen transacties gevonden');
            } else {
                toast.error(`${importResult.parsed} transacties gevonden, maar geen matches in database`);
            }
        } catch (error) {
            console.error('Error importing PayPal:', error);
            toast.error('Fout bij het importeren van PayPal transacties');
        } finally {
            setIsLoading(false);
        }
    };

    const handleClose = () => {
        setPastedText('');
        setSelectedFile(null);
        setResult(null);
        onClose();
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            if (!file.name.toLowerCase().endsWith('.csv')) {
                toast.error('Selecteer een CSV bestand');
                return;
            }
            setSelectedFile(file);
            setResult(null);
        }
    };

    const handleDrop = (e: React.DragEvent<HTMLDivElement>) => {
        e.preventDefault();
        const file = e.dataTransfer.files[0];
        if (file) {
            if (!file.name.toLowerCase().endsWith('.csv')) {
                toast.error('Selecteer een CSV bestand');
                return;
            }
            setSelectedFile(file);
            setResult(null);
        }
    };

    const handleModeChange = (newMode: ImportMode) => {
        setMode(newMode);
        setResult(null);
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">
                            PayPal Transacties Importeren
                        </h2>
                        <p className="text-sm text-gray-500 mt-1">
                            Importeer PayPal betalingen via CSV of copy-paste
                        </p>
                    </div>
                    <button
                        onClick={handleClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <X className="w-6 h-6" />
                    </button>
                </div>

                {/* Tabs */}
                <div className="flex border-b">
                    <button
                        onClick={() => handleModeChange('csv')}
                        className={`flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 transition-colors ${
                            mode === 'csv'
                                ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        <FileSpreadsheet className="w-4 h-4" />
                        CSV Upload (Aanbevolen)
                    </button>
                    <button
                        onClick={() => handleModeChange('paste')}
                        className={`flex-1 px-4 py-3 text-sm font-medium flex items-center justify-center gap-2 transition-colors ${
                            mode === 'paste'
                                ? 'text-blue-600 border-b-2 border-blue-600 bg-blue-50'
                                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50'
                        }`}
                    >
                        <FileText className="w-4 h-4" />
                        Web Paste
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-6">
                    {mode === 'csv' ? (
                        <>
                            {/* CSV Instructions */}
                            <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h3 className="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                                    <FileSpreadsheet className="w-5 h-5" />
                                    Instructies CSV Export
                                </h3>
                                <ol className="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                                    <li>Ga naar PayPal.com → Activiteit → Rapporten downloaden</li>
                                    <li>Kies datumbereik (max 12 maanden)</li>
                                    <li>Selecteer CSV formaat en download</li>
                                    <li>Upload het bestand hieronder</li>
                                </ol>
                            </div>

                            {/* File Upload */}
                            <div
                                className={`mb-6 border-2 border-dashed rounded-lg p-8 text-center transition-colors ${
                                    selectedFile
                                        ? 'border-green-300 bg-green-50'
                                        : 'border-gray-300 hover:border-blue-400 hover:bg-blue-50'
                                }`}
                                onDrop={handleDrop}
                                onDragOver={(e) => e.preventDefault()}
                            >
                                <input
                                    ref={fileInputRef}
                                    type="file"
                                    accept=".csv,.CSV,text/csv"
                                    onChange={handleFileChange}
                                    className="hidden"
                                />
                                {selectedFile ? (
                                    <div className="flex flex-col items-center gap-2">
                                        <CheckCircle className="w-12 h-12 text-green-500" />
                                        <p className="text-green-700 font-medium">{selectedFile.name}</p>
                                        <button
                                            onClick={() => setSelectedFile(null)}
                                            className="text-sm text-gray-500 hover:text-gray-700"
                                        >
                                            Ander bestand kiezen
                                        </button>
                                    </div>
                                ) : (
                                    <div className="flex flex-col items-center gap-2">
                                        <Upload className="w-12 h-12 text-gray-400" />
                                        <p className="text-gray-600">
                                            Sleep een CSV bestand hierheen of{' '}
                                            <button
                                                onClick={() => fileInputRef.current?.click()}
                                                className="text-blue-600 hover:text-blue-700 font-medium"
                                            >
                                                klik om te selecteren
                                            </button>
                                        </p>
                                        <p className="text-sm text-gray-400">
                                            PayPal CSV export bestand
                                        </p>
                                    </div>
                                )}
                            </div>
                        </>
                    ) : (
                        <>
                            {/* Paste Instructions */}
                            <div className="mb-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                <h3 className="font-semibold text-yellow-900 mb-2 flex items-center gap-2">
                                    <AlertCircle className="w-5 h-5" />
                                    Let op: CSV is betrouwbaarder
                                </h3>
                                <p className="text-sm text-yellow-800 mb-2">
                                    De web paste methode werkt mogelijk niet goed met alle PayPal pagina layouts.
                                    Gebruik bij voorkeur de CSV export.
                                </p>
                            </div>

                            <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h3 className="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                                    <FileText className="w-5 h-5" />
                                    Instructies Web Paste
                                </h3>
                                <ol className="text-sm text-blue-800 space-y-1 list-decimal list-inside">
                                    <li>Ga naar PayPal.com → Activiteit</li>
                                    <li>Selecteer alle transacties (Ctrl+A / Cmd+A)</li>
                                    <li>Kopieer de tekst (Ctrl+C / Cmd+C)</li>
                                    <li>Plak hieronder en klik op "Importeren"</li>
                                </ol>
                            </div>

                            {/* Textarea */}
                            <div className="mb-6">
                                <label htmlFor="paypal-paste" className="block text-sm font-medium text-gray-700 mb-2">
                                    PayPal Transacties
                                </label>
                                <textarea
                                    id="paypal-paste"
                                    rows={15}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                                    placeholder="Plak hier de tekst uit je PayPal activiteit..."
                                    value={pastedText}
                                    onChange={(e) => setPastedText(e.target.value)}
                                />
                            </div>
                        </>
                    )}

                    {/* Result */}
                    {result && (
                        <div className={`mb-6 rounded-lg p-4 ${
                            result.imported > 0
                                ? 'bg-green-50 border border-green-200'
                                : 'bg-yellow-50 border border-yellow-200'
                        }`}>
                            <h3 className={`font-semibold mb-2 flex items-center gap-2 ${
                                result.imported > 0 ? 'text-green-900' : 'text-yellow-900'
                            }`}>
                                {result.imported > 0 ? (
                                    <>
                                        <CheckCircle className="w-5 h-5" />
                                        Import Succesvol
                                    </>
                                ) : (
                                    <>
                                        <AlertCircle className="w-5 h-5" />
                                        Geen Matches
                                    </>
                                )}
                            </h3>
                            <div className={`text-sm space-y-1 ${
                                result.imported > 0 ? 'text-green-800' : 'text-yellow-800'
                            }`}>
                                <div>✓ <strong>{result.parsed}</strong> transacties geparsed</div>
                                <div>✓ <strong>{result.matched}</strong> matches gevonden</div>
                                <div>✓ <strong>{result.imported}</strong> child transacties aangemaakt</div>
                                {result.skipped > 0 && (
                                    <div>⚠ <strong>{result.skipped}</strong> overgeslagen (geen match in database)</div>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-3 p-6 border-t bg-gray-50">
                    <button
                        onClick={handleClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                        disabled={isLoading}
                    >
                        Sluiten
                    </button>
                    <button
                        onClick={handleImport}
                        disabled={isLoading || (mode === 'csv' ? !selectedFile : !pastedText.trim())}
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        {isLoading ? 'Importeren...' : 'Importeren'}
                    </button>
                </div>
            </div>
        </div>
    );
}
