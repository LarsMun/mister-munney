import { useState } from 'react';
import { useAuth } from '../shared/contexts/AuthContext';
import toast from 'react-hot-toast';
import logo from '../assets/mister-munney-logo.png';

export default function AuthScreen() {
    const { login, register } = useAuth();
    const [mode, setMode] = useState<'login' | 'register'>('login');
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    const isLogin = mode === 'login';

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Validation
        if (!email || !password) {
            toast.error('Vul alle velden in');
            return;
        }

        if (!isLogin && password !== confirmPassword) {
            toast.error('Wachtwoorden komen niet overeen');
            return;
        }

        if (!isLogin && password.length < 8) {
            toast.error('Wachtwoord moet minimaal 8 tekens zijn');
            return;
        }

        setIsLoading(true);
        try {
            if (isLogin) {
                await login(email, password);
                toast.success('Welkom terug!');
            } else {
                await register(email, password);
                toast.success('Account aangemaakt en ingelogd!');
            }
        } catch (error: any) {
            toast.error(error.message || 'Er is iets misgegaan');
        } finally {
            setIsLoading(false);
        }
    };

    const toggleMode = () => {
        setMode(isLogin ? 'register' : 'login');
        setEmail('');
        setPassword('');
        setConfirmPassword('');
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center p-4">
            <div className="max-w-md w-full bg-white rounded-lg shadow-xl p-8">
                {/* Logo */}
                <div className="text-center mb-8">
                    <img src={logo} alt="Mister Munney" className="h-32 w-auto mx-auto mb-4" />
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">
                        {isLogin ? 'Inloggen' : 'Registreren'}
                    </h1>
                    <p className="text-gray-600">
                        {isLogin
                            ? 'Log in om je financiën te beheren'
                            : 'Maak een account aan om te beginnen'}
                    </p>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Email */}
                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-1">
                            E-mailadres
                        </label>
                        <input
                            id="email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            disabled={isLoading}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                            placeholder="jouw@email.nl"
                            autoComplete="email"
                        />
                    </div>

                    {/* Password */}
                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-1">
                            Wachtwoord
                        </label>
                        <input
                            id="password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            disabled={isLoading}
                            className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                            placeholder={isLogin ? 'Wachtwoord' : 'Minimaal 8 tekens'}
                            autoComplete={isLogin ? 'current-password' : 'new-password'}
                        />
                    </div>

                    {/* Confirm Password (only for register) */}
                    {!isLogin && (
                        <div>
                            <label htmlFor="confirmPassword" className="block text-sm font-medium text-gray-700 mb-1">
                                Bevestig wachtwoord
                            </label>
                            <input
                                id="confirmPassword"
                                type="password"
                                value={confirmPassword}
                                onChange={(e) => setConfirmPassword(e.target.value)}
                                disabled={isLoading}
                                className="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:opacity-50 disabled:cursor-not-allowed"
                                placeholder="Herhaal wachtwoord"
                                autoComplete="new-password"
                            />
                        </div>
                    )}

                    {/* Submit Button */}
                    <button
                        type="submit"
                        disabled={isLoading}
                        className="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center"
                    >
                        {isLoading ? (
                            <>
                                <svg className="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {isLogin ? 'Inloggen...' : 'Registreren...'}
                            </>
                        ) : (
                            isLogin ? 'Inloggen' : 'Account aanmaken'
                        )}
                    </button>
                </form>

                {/* Toggle Mode */}
                <div className="mt-6 text-center">
                    <p className="text-sm text-gray-600">
                        {isLogin ? 'Nog geen account?' : 'Heb je al een account?'}
                        {' '}
                        <button
                            type="button"
                            onClick={toggleMode}
                            disabled={isLoading}
                            className="text-blue-600 hover:text-blue-700 font-medium disabled:opacity-50"
                        >
                            {isLogin ? 'Registreer hier' : 'Log hier in'}
                        </button>
                    </p>
                </div>
            </div>
        </div>
    );
}
