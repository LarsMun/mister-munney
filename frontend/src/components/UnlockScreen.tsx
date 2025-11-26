import { useState, useEffect, useCallback } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import logo from '../assets/mister-munney-logo.png';

export default function UnlockScreen() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [isUnlocking, setIsUnlocking] = useState(false);
    const [unlocked, setUnlocked] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const token = searchParams.get('token');

    const unlockAccount = useCallback(async (unlockToken: string) => {
        setIsUnlocking(true);
        setError(null);

        try {
            const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';
            const response = await fetch(`${API_URL}/unlock`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ token: unlockToken }),
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 400) {
                    setError('De unlock link is verlopen. Vraag een nieuwe unlock email aan.');
                } else if (response.status === 404) {
                    setError('Ongeldige unlock link.');
                } else {
                    setError(data.message || 'Er is een fout opgetreden bij het unlocken van je account.');
                }
                return;
            }

            // Success!
            setUnlocked(true);
            toast.success('Je account is succesvol ontgrendeld! Je kunt nu inloggen.');

            // Redirect to login after 3 seconds
            setTimeout(() => {
                navigate('/');
            }, 3000);
        } catch (err) {
            console.error('Unlock error:', err);
            setError('Er is een fout opgetreden bij het verbinden met de server. Probeer het later opnieuw.');
        } finally {
            setIsUnlocking(false);
        }
    }, [navigate]);

    useEffect(() => {
        if (!token) {
            setError('Geen unlock token gevonden in de URL');
            return;
        }

        unlockAccount(token);
    }, [token, unlockAccount]);

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center p-4">
            <div className="max-w-md w-full">
                {/* Logo */}
                <div className="text-center mb-8">
                    <img
                        src={logo}
                        alt="Mister Munney"
                        className="h-32 w-auto mx-auto mb-4"
                    />
                    <h1 className="text-3xl font-bold text-gray-800">Account Ontgrendelen</h1>
                </div>

                {/* Content Card */}
                <div className="bg-white rounded-2xl shadow-xl p-8">
                    {isUnlocking && (
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-16 w-16 border-b-4 border-blue-600 mx-auto mb-4"></div>
                            <p className="text-gray-600">Je account wordt ontgrendeld...</p>
                        </div>
                    )}

                    {!isUnlocking && unlocked && (
                        <div className="text-center">
                            <div className="bg-green-100 rounded-full h-16 w-16 flex items-center justify-center mx-auto mb-4">
                                <svg className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <h2 className="text-2xl font-bold text-gray-800 mb-2">Account Ontgrendeld!</h2>
                            <p className="text-gray-600 mb-6">
                                Je account is succesvol ontgrendeld. Je wordt automatisch doorgestuurd naar de login pagina.
                            </p>
                            <button
                                onClick={() => navigate('/')}
                                className="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium"
                            >
                                Nu inloggen
                            </button>
                        </div>
                    )}

                    {!isUnlocking && error && (
                        <div className="text-center">
                            <div className="bg-red-100 rounded-full h-16 w-16 flex items-center justify-center mx-auto mb-4">
                                <svg className="h-8 w-8 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </div>
                            <h2 className="text-2xl font-bold text-gray-800 mb-2">Ontgrendelen Mislukt</h2>
                            <p className="text-red-600 mb-6">{error}</p>
                            <div className="space-y-3">
                                <button
                                    onClick={() => navigate('/')}
                                    className="w-full bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium"
                                >
                                    Terug naar login
                                </button>
                                <p className="text-sm text-gray-500">
                                    Als je de unlock link verloren bent, kun je een nieuwe aanvragen door 5x fout in te loggen.
                                </p>
                            </div>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <p className="text-center text-gray-600 mt-6 text-sm">
                    © {new Date().getFullYear()} Mister Munney
                </p>
            </div>
        </div>
    );
}
