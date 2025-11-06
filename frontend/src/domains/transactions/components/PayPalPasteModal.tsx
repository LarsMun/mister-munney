import { useState } from 'react';
import { X, FileText, CheckCircle, AlertCircle } from 'lucide-react';
import toast from 'react-hot-toast';
import { importPayPalTransactions } from '../services/PayPalService';

interface PayPalPasteModalProps {
    isOpen: boolean;
    onClose: () => void;
    accountId: number;
    onSuccess: () => void;
}

export default function PayPalPasteModal({
    isOpen,
    onClose,
    accountId,
    onSuccess,
}: PayPalPasteModalProps) {
    const [pastedText, setPastedText] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [result, setResult] = useState<{
        parsed: number;
        matched: number;
        imported: number;
        skipped: number;
    } | null>(null);

    if (!isOpen) return null;

    const handleImport = async () => {
        if (!pastedText.trim()) {
            toast.error('Plak eerst de transacties uit PayPal');
            return;
        }

        setIsLoading(true);
        try {
            const importResult = await importPayPalTransactions(accountId, pastedText);
            setResult(importResult);

            if (importResult.imported > 0) {
                toast.success(`${importResult.imported} PayPal transacties geïmporteerd`);
                onSuccess();
            } else if (importResult.parsed === 0) {
                toast.error('Geen transacties gevonden in de tekst');
            } else {
                toast.warning(`${importResult.parsed} transacties gevonden, maar geen matches in database`);
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
        setResult(null);
        onClose();
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
                            Importeer PayPal betalingen via copy-paste
                        </p>
                    </div>
                    <button
                        onClick={handleClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <X className="w-6 h-6" />
                    </button>
                </div>

                {/* Content */}
                <div className="flex-1 overflow-y-auto p-6">
                    {/* Instructions */}
                    <div className="mb-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 className="font-semibold text-blue-900 mb-2 flex items-center gap-2">
                            <FileText className="w-5 h-5" />
                            Instructies
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
                        disabled={isLoading || !pastedText.trim()}
                        className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed"
                    >
                        {isLoading ? 'Importeren...' : 'Importeren'}
                    </button>
                </div>
            </div>
        </div>
    );
}
