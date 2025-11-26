import { useState } from "react";
import api from "../lib/axios";
import toast from "react-hot-toast";
import { useAuth } from "../shared/contexts/AuthContext";

interface WelcomeScreenProps {
    onAccountCreated: () => void;
}

export default function WelcomeScreen({ onAccountCreated }: WelcomeScreenProps) {
    const { logout, user } = useAuth();
    const [file, setFile] = useState<File | null>(null);
    const [isUploading, setIsUploading] = useState(false);

    const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
        const selectedFile = event.target.files?.[0];

        if (selectedFile) {
            // Accepteer CSV bestanden op basis van extensie én MIME type
            const isCSV = selectedFile.name.toLowerCase().endsWith('.csv') ||
                selectedFile.type === 'text/csv' ||
                selectedFile.type === 'application/vnd.ms-excel' ||
                selectedFile.type === 'text/plain';

            if (isCSV) {
                setFile(selectedFile);
                toast.success(`Bestand geselecteerd: ${selectedFile.name}`);
            } else {
                toast.error(`Ongeldig bestandstype: ${selectedFile.type}. Selecteer een CSV-bestand.`);
            }
        }
    };

    const handleUpload = async () => {
        if (!file) {
            toast.error('Selecteer eerst een CSV-bestand');
            return;
        }

        setIsUploading(true);
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await api.post('/transactions/import-first', formData, {
                headers: {
                    'Content-Type': 'multipart/form-data',
                },
            });

            const { imported, skipped, duplicates } = response.data;
            if (imported > 0) {
                toast.success(`${imported} transacties succesvol geïmporteerd! Accounts zijn automatisch aangemaakt.${skipped || duplicates > 0 ? ` (${skipped || duplicates} overgeslagen)` : ''}`);
            } else {
                toast.success('CSV-bestand verwerkt, geen nieuwe transacties gevonden.');
            }

            // Trigger the account refresh and UI update
            onAccountCreated();
        } catch (error: unknown) {
            console.error('Upload error:', error);

            let errorMessage = 'Fout bij uploaden van CSV-bestand';

            const err = error as { response?: { data?: { error?: string } }; message?: string };
            if (err.response?.data?.error) {
                errorMessage = err.response.data.error;
            } else if (err.message) {
                errorMessage = err.message;
            }

            toast.error(errorMessage);
        } finally {
            setIsUploading(false);
        }
    };

    return (
        <div className="max-w-2xl mx-auto p-8 bg-white rounded-lg shadow-lg">
            {/* Logout button in top-right corner */}
            <div className="flex justify-end mb-4">
                <div className="flex items-center gap-3 text-sm">
                    <span className="text-gray-600">{user?.email}</span>
                    <button
                        onClick={logout}
                        className="px-3 py-1 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded transition-colors"
                        title="Uitloggen"
                    >
                        Uitloggen
                    </button>
                </div>
            </div>

            <div className="text-center mb-8">
                <h1 className="text-3xl font-bold text-gray-800 mb-4">
                    Welkom bij Munney!
                </h1>
                <p className="text-gray-600 mb-6">
                    Upload je bankrekening CSV-bestand om te beginnen. Accounts worden automatisch aangemaakt uit je transacties.
                </p>
            </div>

            <div className="space-y-6">
                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 hover:border-gray-400 transition-colors">
                    <div className="text-center">
                        <div className="mb-4">
                            <input
                                type="file"
                                accept=".csv,text/csv,application/vnd.ms-excel,text/plain"
                                onChange={handleFileSelect}
                                className="hidden"
                                id="csv-upload"
                                disabled={isUploading}
                            />
                            <label
                                htmlFor="csv-upload"
                                className={`cursor-pointer inline-block px-6 py-3 rounded-lg font-medium transition-colors duration-200 ${
                                    isUploading
                                        ? 'bg-gray-400 text-gray-600 cursor-not-allowed'
                                        : 'bg-blue-500 hover:bg-blue-600 text-white'
                                }`}
                            >
                                {isUploading ? 'Bezig met uploaden...' : 'Selecteer CSV-bestand'}
                            </label>
                        </div>

                        {file && (
                            <div className="mt-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                                <div className="flex items-center justify-center space-x-2 text-green-800">
                                    <svg className="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                    <div>
                                        <p className="font-medium">{file.name}</p>
                                        <p className="text-sm text-green-600">
                                            Grootte: {(file.size / 1024).toFixed(1)} KB
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </div>

                <div className="text-center">
                    <button
                        onClick={handleUpload}
                        disabled={!file || isUploading}
                        className={`px-8 py-3 rounded-lg font-medium transition-colors duration-200 ${
                            !file || isUploading
                                ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                                : 'bg-green-500 hover:bg-green-600 text-white shadow-lg hover:shadow-xl'
                        }`}
                    >
                        {isUploading ? (
                            <span className="flex items-center justify-center">
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Transacties importeren...
                            </span>
                        ) : (
                            'Importeer Transacties'
                        )}
                    </button>
                </div>

                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 className="text-sm font-medium text-blue-800 mb-2">
                        Ondersteunde banken:
                    </h3>
                    <ul className="text-sm text-blue-600 space-y-1">
                        <li>• CSV-bestanden van Nederlandse banken</li>
                        <li>• ING, ABN AMRO, Rabobank, SNS Bank</li>
                        <li>• Andere banken met standaard CSV-formaat</li>
                    </ul>
                    <div className="mt-3 pt-3 border-t border-blue-200">
                        <p className="text-xs text-blue-600">
                            Accounts worden automatisch aangemaakt op basis van de rekeningnummers in je CSV.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}