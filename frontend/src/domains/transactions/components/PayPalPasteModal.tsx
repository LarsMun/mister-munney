import { useState, useRef, useEffect, useMemo } from 'react';
import { X, FileText, CheckCircle, AlertCircle, Upload, FileSpreadsheet, Link2 } from 'lucide-react';
import toast from 'react-hot-toast';
import {
    importPayPalTransactions,
    importPayPalCsv,
    parsePayPalCsv,
    getUnmatchedPayPalTransactions,
    createPayPalLinks,
    ParsedPayPalItem,
    BankPayPalTransaction,
} from '../services/PayPalService';

interface PayPalPasteModalProps {
    isOpen: boolean;
    onClose: () => void;
    accountId: number;
    onSuccess: () => void;
}

type ImportMode = 'csv' | 'paste';
type Phase = 'upload' | 'matching';

export default function PayPalPasteModal({
    isOpen,
    onClose,
    accountId,
    onSuccess,
}: PayPalPasteModalProps) {
    const [mode, setMode] = useState<ImportMode>('csv');
    const [phase, setPhase] = useState<Phase>('upload');
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

    // Matching phase state
    const [paypalItems, setPaypalItems] = useState<ParsedPayPalItem[]>([]);
    const [bankTransactions, setBankTransactions] = useState<BankPayPalTransaction[]>([]);
    const [selectedBankTxId, setSelectedBankTxId] = useState<number | null>(null);
    const [selectedItemIds, setSelectedItemIds] = useState<Set<string>>(new Set());
    const [isLinking, setIsLinking] = useState(false);

    // Calculate selected amounts
    const selectedPayPalTotal = useMemo(() => {
        return paypalItems
            .filter((item) => selectedItemIds.has(item.id))
            .reduce((sum, item) => sum + Math.abs(item.amount), 0);
    }, [paypalItems, selectedItemIds]);

    const selectedBankTx = useMemo(() => {
        return bankTransactions.find((tx) => tx.id === selectedBankTxId);
    }, [bankTransactions, selectedBankTxId]);

    const amountDiff = useMemo(() => {
        if (!selectedBankTx) return 0;
        return Math.abs(selectedBankTx.amount) - selectedPayPalTotal;
    }, [selectedBankTx, selectedPayPalTotal]);

    // Load bank transactions when entering matching phase
    useEffect(() => {
        if (phase === 'matching' && bankTransactions.length === 0) {
            loadBankTransactions();
        }
    }, [phase]);

    const loadBankTransactions = async () => {
        try {
            const result = await getUnmatchedPayPalTransactions(accountId);
            setBankTransactions(result.transactions);
            if (result.transactions.length > 0) {
                setSelectedBankTxId(result.transactions[0].id);
            }
        } catch (error) {
            console.error('Error loading bank transactions:', error);
            toast.error('Fout bij laden van banktransacties');
        }
    };

    if (!isOpen) return null;

    const handleImport = async () => {
        if (mode === 'csv') {
            if (!selectedFile) {
                toast.error('Selecteer eerst een CSV bestand');
                return;
            }

            // CSV mode: parse and go to matching phase
            setIsLoading(true);
            try {
                const parseResult = await parsePayPalCsv(accountId, selectedFile);

                if (parseResult.count === 0) {
                    toast.error('Geen transacties gevonden in CSV');
                    return;
                }

                setPaypalItems(parseResult.items);
                setPhase('matching');

                if (parseResult.alreadyLinked > 0) {
                    toast.success(`${parseResult.count} nieuwe transacties, ${parseResult.alreadyLinked} al gekoppeld`);
                } else {
                    toast.success(`${parseResult.count} transacties gevonden`);
                }
            } catch (error) {
                console.error('Error parsing PayPal CSV:', error);
                toast.error('Fout bij het parsen van PayPal CSV');
            } finally {
                setIsLoading(false);
            }
        } else {
            // Paste mode: use old auto-import flow
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
        }
    };

    const handleLink = async () => {
        if (!selectedBankTxId || selectedItemIds.size === 0) {
            toast.error('Selecteer een banktransactie en PayPal items');
            return;
        }

        setIsLinking(true);
        try {
            const itemsToLink = paypalItems
                .filter((item) => selectedItemIds.has(item.id))
                .map((item) => ({
                    date: item.date,
                    merchant: item.merchant,
                    amount: item.amount,
                    reference: item.reference,
                }));

            const result = await createPayPalLinks(accountId, selectedBankTxId, itemsToLink);

            toast.success(`${result.created} transacties gekoppeld`);

            // Remove linked items from list
            setPaypalItems((prev) => prev.filter((item) => !selectedItemIds.has(item.id)));
            setSelectedItemIds(new Set());

            // Refresh bank transactions
            await loadBankTransactions();

            onSuccess();
        } catch (error) {
            console.error('Error linking PayPal items:', error);
            toast.error('Fout bij het koppelen van transacties');
        } finally {
            setIsLinking(false);
        }
    };

    const handleClose = () => {
        setPastedText('');
        setSelectedFile(null);
        setResult(null);
        setPhase('upload');
        setPaypalItems([]);
        setBankTransactions([]);
        setSelectedBankTxId(null);
        setSelectedItemIds(new Set());
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

    const toggleItemSelection = (itemId: string) => {
        setSelectedItemIds((prev) => {
            const next = new Set(prev);
            if (next.has(itemId)) {
                next.delete(itemId);
            } else {
                next.add(itemId);
            }
            return next;
        });
    };

    const selectAllItems = () => {
        setSelectedItemIds(new Set(paypalItems.map((item) => item.id)));
    };

    const selectNoneItems = () => {
        setSelectedItemIds(new Set());
    };

    const formatAmount = (amount: number) => {
        return new Intl.NumberFormat('nl-NL', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (dateStr: string) => {
        const date = new Date(dateStr);
        return date.toLocaleDateString('nl-NL', {
            day: 'numeric',
            month: 'short',
        });
    };

    // Matching Phase UI
    if (phase === 'matching') {
        return (
            <div className="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                <div className="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] overflow-hidden flex flex-col">
                    {/* Header */}
                    <div className="flex items-center justify-between p-6 border-b">
                        <div>
                            <h2 className="text-xl font-semibold text-gray-900">
                                PayPal Transacties Koppelen
                            </h2>
                            <p className="text-sm text-gray-500 mt-1">
                                Selecteer een banktransactie en kies de bijbehorende PayPal items
                            </p>
                        </div>
                        <button
                            onClick={handleClose}
                            className="text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <X className="w-6 h-6" />
                        </button>
                    </div>

                    {/* Two-panel content */}
                    <div className="flex-1 overflow-hidden flex">
                        {/* Left panel: Bank transactions */}
                        <div className="w-1/2 border-r flex flex-col">
                            <div className="p-4 bg-gray-50 border-b">
                                <h3 className="font-medium text-gray-700">Banktransacties</h3>
                                <p className="text-xs text-gray-500">
                                    {bankTransactions.length} transacties met "PayPal"
                                </p>
                            </div>
                            <div className="flex-1 overflow-y-auto">
                                {bankTransactions.length === 0 ? (
                                    <div className="p-4 text-center text-gray-500">
                                        Geen ontkoppelde PayPal transacties gevonden
                                    </div>
                                ) : (
                                    <div className="divide-y">
                                        {bankTransactions.map((tx) => (
                                            <label
                                                key={tx.id}
                                                className={`flex items-start gap-3 p-4 cursor-pointer hover:bg-gray-50 transition-colors ${
                                                    selectedBankTxId === tx.id ? 'bg-blue-50' : ''
                                                }`}
                                            >
                                                <input
                                                    type="radio"
                                                    name="bankTx"
                                                    checked={selectedBankTxId === tx.id}
                                                    onChange={() => setSelectedBankTxId(tx.id)}
                                                    className="mt-1"
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex justify-between items-start">
                                                        <span className="text-sm text-gray-500">
                                                            {formatDate(tx.date)}
                                                        </span>
                                                        <span className="font-medium text-red-600">
                                                            {formatAmount(tx.amount)}
                                                        </span>
                                                    </div>
                                                    <p className="text-sm text-gray-900 truncate">
                                                        {tx.description}
                                                    </p>
                                                    <p className="text-xs text-gray-400">
                                                        {tx.splitCount} splits
                                                    </p>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Right panel: PayPal items */}
                        <div className="w-1/2 flex flex-col">
                            <div className="p-4 bg-gray-50 border-b flex items-center justify-between">
                                <div>
                                    <h3 className="font-medium text-gray-700">PayPal Items</h3>
                                    <p className="text-xs text-gray-500">
                                        {paypalItems.length} items uit CSV
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <button
                                        onClick={selectAllItems}
                                        className="text-xs text-blue-600 hover:text-blue-700"
                                    >
                                        Alles
                                    </button>
                                    <span className="text-gray-300">|</span>
                                    <button
                                        onClick={selectNoneItems}
                                        className="text-xs text-blue-600 hover:text-blue-700"
                                    >
                                        Niets
                                    </button>
                                </div>
                            </div>
                            <div className="flex-1 overflow-y-auto">
                                {paypalItems.length === 0 ? (
                                    <div className="p-4 text-center text-gray-500">
                                        <CheckCircle className="w-8 h-8 mx-auto mb-2 text-green-500" />
                                        Alle PayPal items zijn gekoppeld!
                                    </div>
                                ) : (
                                    <div className="divide-y">
                                        {paypalItems.map((item) => (
                                            <label
                                                key={item.id}
                                                className={`flex items-start gap-3 p-4 cursor-pointer hover:bg-gray-50 transition-colors ${
                                                    selectedItemIds.has(item.id) ? 'bg-green-50' : ''
                                                }`}
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={selectedItemIds.has(item.id)}
                                                    onChange={() => toggleItemSelection(item.id)}
                                                    className="mt-1"
                                                />
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex justify-between items-start">
                                                        <span className="text-sm text-gray-500">
                                                            {formatDate(item.date)}
                                                        </span>
                                                        <span className="font-medium text-red-600">
                                                            {formatAmount(item.amount)}
                                                        </span>
                                                    </div>
                                                    <p className="text-sm text-gray-900 truncate">
                                                        {item.merchant}
                                                    </p>
                                                    {item.type && (
                                                        <p className="text-xs text-gray-400">
                                                            {item.type}
                                                        </p>
                                                    )}
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Summary bar */}
                    <div className="p-4 bg-gray-100 border-t">
                        <div className="flex items-center justify-between">
                            <div className="flex gap-6 text-sm">
                                <div>
                                    <span className="text-gray-500">Geselecteerd:</span>{' '}
                                    <span className="font-medium">
                                        {formatAmount(-selectedPayPalTotal)}
                                    </span>
                                </div>
                                {selectedBankTx && (
                                    <>
                                        <div>
                                            <span className="text-gray-500">Banktransactie:</span>{' '}
                                            <span className="font-medium">
                                                {formatAmount(selectedBankTx.amount)}
                                            </span>
                                        </div>
                                        <div>
                                            <span className="text-gray-500">Verschil:</span>{' '}
                                            <span
                                                className={`font-medium ${
                                                    Math.abs(amountDiff) < 0.01
                                                        ? 'text-green-600'
                                                        : 'text-orange-600'
                                                }`}
                                            >
                                                {formatAmount(amountDiff)}
                                            </span>
                                        </div>
                                    </>
                                )}
                            </div>
                            <div className="flex gap-3">
                                <button
                                    onClick={() => setPhase('upload')}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
                                >
                                    Terug
                                </button>
                                <button
                                    onClick={handleLink}
                                    disabled={
                                        isLinking ||
                                        !selectedBankTxId ||
                                        selectedItemIds.size === 0
                                    }
                                    className="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed flex items-center gap-2"
                                >
                                    <Link2 className="w-4 h-4" />
                                    {isLinking
                                        ? 'Koppelen...'
                                        : `${selectedItemIds.size} items koppelen`}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    // Upload Phase UI (original)
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

                    {/* Result (only for paste mode) */}
                    {result && mode === 'paste' && (
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
                        {isLoading ? 'Verwerken...' : mode === 'csv' ? 'Volgende' : 'Importeren'}
                    </button>
                </div>
            </div>
        </div>
    );
}
