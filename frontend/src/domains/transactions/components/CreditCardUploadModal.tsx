import { useState } from 'react';
import { X, Upload, FileText, AlertCircle, CheckCircle } from 'lucide-react';
import toast from 'react-hot-toast';
import { parseCreditCardPdf, ParseResult } from '../services/TransactionSplitService';

interface CreditCardUploadModalProps {
    isOpen: boolean;
    onClose: () => void;
    accountId: number;
    transactionId: number;
    transactionAmount: number;
    onParsed: (result: ParseResult) => void;
}

export default function CreditCardUploadModal({
    isOpen,
    onClose,
    accountId,
    transactionId,
    transactionAmount,
    onParsed,
}: CreditCardUploadModalProps) {
    const [pdfText, setPdfText] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [parseResult, setParseResult] = useState<ParseResult | null>(null);

    if (!isOpen) return null;

    const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        if (!file.name.toLowerCase().endsWith('.pdf')) {
            toast.error('Alleen PDF bestanden zijn toegestaan');
            return;
        }

        toast.info('PDF geüpload. Kopieer de tekst uit het PDF bestand en plak deze hieronder.');
    };

    const handleParse = async () => {
        if (!pdfText.trim()) {
            toast.error('Plak eerst de tekst uit het creditcard afschrift');
            return;
        }

        setIsLoading(true);
        try {
            const result = await parseCreditCardPdf(accountId, transactionId, pdfText);
            setParseResult(result);

            if (result.valid) {
                toast.success(`${result.count} transacties gevonden`);
            } else {
                toast.error(
                    `Totaalbedrag (€${result.total.toFixed(2)}) komt niet overeen met incasso (€${result.parentAmount.toFixed(2)})`
                );
            }
        } catch (error) {
            console.error('Error parsing PDF:', error);
            toast.error('Fout bij het verwerken van het afschrift');
        } finally {
            setIsLoading(false);
        }
    };

    const handleConfirm = () => {
        if (parseResult && parseResult.valid) {
            onParsed(parseResult);
            handleClose();
        }
    };

    const handleClose = () => {
        setPdfText('');
        setParseResult(null);
        onClose();
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
                {/* Header */}
                <div className="flex items-center justify-between p-6 border-b">
                    <div>
                        <h2 className="text-xl font-semibold text-gray-900">
                            Creditcard Afschrift Importeren
                        </h2>
                        <p className="text-sm text-gray-500 mt-1">
                            Incasso bedrag: €{Math.abs(transactionAmount).toFixed(2)}
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
                            <li>Open je ING creditcard afschrift PDF</li>
                            <li>Selecteer alle tekst (Ctrl+A / Cmd+A)</li>
                            <li>Kopieer de tekst (Ctrl+C / Cmd+C)</li>
                            <li>Plak de tekst in het tekstveld hieronder</li>
                            <li>Klik op "Analyseer Afschrift"</li>
                        </ol>
                    </div>

                    {/* File Upload (Optional) */}
                    <div className="mb-6">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            PDF Bestand (optioneel - ter referentie)
                        </label>
                        <div className="flex items-center gap-4">
                            <label className="flex items-center gap-2 px-4 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg cursor-pointer transition-colors">
                                <Upload className="w-4 h-4" />
                                <span className="text-sm">Selecteer PDF</span>
                                <input
                                    type="file"
                                    accept=".pdf"
                                    onChange={handleFileUpload}
                                    className="hidden"
                                />
                            </label>
                        </div>
                    </div>

                    {/* Text Area */}
                    <div className="mb-6">
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Afschrift Tekst *
                        </label>
                        <textarea
                            value={pdfText}
                            onChange={(e) => setPdfText(e.target.value)}
                            placeholder="Plak hier de tekst uit je creditcard afschrift..."
                            className="w-full h-64 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 font-mono text-sm"
                            disabled={isLoading}
                        />
                    </div>

                    {/* Parse Result */}
                    {parseResult && (
                        <div className={`mb-6 rounded-lg p-4 ${
                            parseResult.valid
                                ? 'bg-green-50 border border-green-200'
                                : 'bg-red-50 border border-red-200'
                        }`}>
                            <div className="flex items-start gap-3">
                                {parseResult.valid ? (
                                    <CheckCircle className="w-5 h-5 text-green-600 flex-shrink-0 mt-0.5" />
                                ) : (
                                    <AlertCircle className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" />
                                )}
                                <div className="flex-1">
                                    <h4 className={`font-semibold mb-2 ${
                                        parseResult.valid ? 'text-green-900' : 'text-red-900'
                                    }`}>
                                        {parseResult.valid ? 'Validatie Geslaagd' : 'Validatie Mislukt'}
                                    </h4>
                                    <div className="text-sm space-y-1">
                                        <p className={parseResult.valid ? 'text-green-800' : 'text-red-800'}>
                                            <span className="font-medium">Gevonden transacties:</span> {parseResult.count}
                                        </p>
                                        <p className={parseResult.valid ? 'text-green-800' : 'text-red-800'}>
                                            <span className="font-medium">Totaal:</span> €{parseResult.total.toFixed(2)}
                                        </p>
                                        <p className={parseResult.valid ? 'text-green-800' : 'text-red-800'}>
                                            <span className="font-medium">Incasso bedrag:</span> €{parseResult.parentAmount.toFixed(2)}
                                        </p>
                                        {!parseResult.valid && (
                                            <p className="text-red-800 mt-2">
                                                Het totaal van de gevonden transacties komt niet overeen met het incasso bedrag.
                                            </p>
                                        )}
                                    </div>

                                    {/* Transaction Preview */}
                                    {parseResult.transactions.length > 0 && (
                                        <div className="mt-4">
                                            <p className="font-medium text-sm mb-2">Gevonden transacties:</p>
                                            <div className="bg-white rounded border border-gray-200 max-h-48 overflow-y-auto">
                                                {parseResult.transactions.map((tx, idx) => (
                                                    <div key={idx} className="px-3 py-2 border-b last:border-b-0 text-xs">
                                                        <div className="flex justify-between items-start">
                                                            <div className="flex-1">
                                                                <p className="font-medium text-gray-900">{tx.description}</p>
                                                                <p className="text-gray-500">{tx.date}</p>
                                                            </div>
                                                            <p className={`font-medium ${tx.amount < 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                                €{tx.amount.toFixed(2)}
                                                            </p>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="flex items-center justify-end gap-3 p-6 border-t bg-gray-50">
                    <button
                        onClick={handleClose}
                        className="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"
                        disabled={isLoading}
                    >
                        Annuleren
                    </button>
                    {!parseResult ? (
                        <button
                            onClick={handleParse}
                            disabled={isLoading || !pdfText.trim()}
                            className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                        >
                            {isLoading ? (
                                <>
                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white"></div>
                                    Analyseren...
                                </>
                            ) : (
                                <>
                                    <FileText className="w-4 h-4" />
                                    Analyseer Afschrift
                                </>
                            )}
                        </button>
                    ) : (
                        <button
                            onClick={handleConfirm}
                            disabled={!parseResult.valid}
                            className="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                        >
                            <CheckCircle className="w-4 h-4" />
                            Splits Aanmaken
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
