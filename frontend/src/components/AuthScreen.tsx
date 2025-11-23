import { useState, useRef } from 'react';
import { useAuth } from '../shared/contexts/AuthContext';
import toast from 'react-hot-toast';
import logo from '../assets/mister-munney-logo.png';
import HCaptcha from '@hcaptcha/react-hcaptcha';

const HCAPTCHA_SITE_KEY = '89d8116c-c1de-4818-b25c-1abe39bed464';

export default function AuthScreen() {
    const { login } = useAuth();
    // Registration disabled - always in login mode
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [requiresCaptcha, setRequiresCaptcha] = useState(false);
    const [captchaToken, setCaptchaToken] = useState<string | null>(null);
    const captchaRef = useRef<HCaptcha>(null);

    const isLogin = true; // Always login mode

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();

        // Validation
        if (!email || !password) {
            toast.error('Vul alle velden in');
            return;
        }

        // If CAPTCHA is required but not solved
        if (requiresCaptcha && !captchaToken) {
            toast.error('Los eerst de CAPTCHA op');
            return;
        }

        setIsLoading(true);
        try {
            await login(email, password, captchaToken || undefined);
            toast.success('Welkom terug!');
            // Reset CAPTCHA state on successful login
            setRequiresCaptcha(false);
            setCaptchaToken(null);
        } catch (error: any) {
            // Check if CAPTCHA is now required
            if (error.requiresCaptcha) {
                setRequiresCaptcha(true);
                toast.error(`CAPTCHA verificatie vereist (${error.failedAttempts || 3}+ pogingen)`);
                // Reset captcha to allow user to solve it again
                setCaptchaToken(null);
                captchaRef.current?.resetCaptcha();
            } else {
                toast.error(error.message || 'Er is iets misgegaan');
            }
        } finally {
            setIsLoading(false);
        }
    };

    const handleCaptchaVerify = (token: string) => {
        setCaptchaToken(token);
    };

    const handleCaptchaExpire = () => {
        setCaptchaToken(null);
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center p-4">
            <div className="max-w-md w-full bg-white rounded-lg shadow-xl p-8">
                {/* Logo */}
                <div className="text-center mb-8">
                    <img src={logo} alt="Mister Munney" className="h-32 w-auto mx-auto mb-4" />
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">
                        Inloggen
                    </h1>
                    <p className="text-gray-600">
                        Log in om je financiën te beheren
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
                            placeholder="Wachtwoord"
                            autoComplete="current-password"
                        />
                    </div>

                    {/* hCaptcha (shown after 3 failed attempts) */}
                    {requiresCaptcha && (
                        <div className="flex justify-center">
                            <HCaptcha
                                ref={captchaRef}
                                sitekey={HCAPTCHA_SITE_KEY}
                                onVerify={handleCaptchaVerify}
                                onExpire={handleCaptchaExpire}
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
                                Inloggen...
                            </>
                        ) : (
                            'Inloggen'
                        )}
                    </button>
                </form>
            </div>
        </div>
    );
}
