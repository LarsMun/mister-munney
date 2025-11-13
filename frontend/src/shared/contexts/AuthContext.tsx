import { createContext, useContext, useState, useEffect, ReactNode, useCallback } from 'react';
import api from '../../lib/axios';

interface User {
    id: number;
    email: string;
}

interface AuthContextType {
    user: User | null;
    token: string | null;
    isAuthenticated: boolean;
    isLoading: boolean;
    login: (email: string, password: string) => Promise<void>;
    register: (email: string, password: string) => Promise<void>;
    logout: () => void;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

const TOKEN_KEY = 'munney_jwt_token';
const USER_KEY = 'munney_user';

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>(null);
    const [token, setToken] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    // Load token and user from localStorage on mount
    useEffect(() => {
        const storedToken = localStorage.getItem(TOKEN_KEY);
        const storedUser = localStorage.getItem(USER_KEY);

        if (storedToken && storedUser) {
            try {
                setToken(storedToken);
                setUser(JSON.parse(storedUser));
            } catch (error) {
                console.error('Failed to parse stored user:', error);
                localStorage.removeItem(TOKEN_KEY);
                localStorage.removeItem(USER_KEY);
            }
        }
        setIsLoading(false);
    }, []);

    const login = useCallback(async (email: string, password: string) => {
        try {
            const response = await api.post('/login', { email, password });
            const { token: jwtToken } = response.data;

            // Decode JWT to get user info (simple base64 decode of payload)
            const payload = JSON.parse(atob(jwtToken.split('.')[1]));
            const userData: User = {
                id: payload.id || payload.user_id || payload.sub,
                email: payload.email || payload.username || email,
            };

            // Store token and user
            localStorage.setItem(TOKEN_KEY, jwtToken);
            localStorage.setItem(USER_KEY, JSON.stringify(userData));

            setToken(jwtToken);
            setUser(userData);
        } catch (error: any) {
            console.error('Login failed:', error);
            if (error.response?.status === 401) {
                throw new Error('Ongeldige inloggegevens');
            }
            throw new Error('Inloggen mislukt. Probeer het opnieuw.');
        }
    }, []);

    const register = useCallback(async (email: string, password: string) => {
        try {
            const response = await api.post('/register', { email, password });

            // After successful registration, automatically log in
            await login(email, password);
        } catch (error: any) {
            console.error('Registration failed:', error);
            if (error.response?.status === 409) {
                throw new Error('Dit e-mailadres is al geregistreerd');
            }
            if (error.response?.data?.error) {
                throw new Error(error.response.data.error);
            }
            throw new Error('Registreren mislukt. Probeer het opnieuw.');
        }
    }, [login]);

    const logout = useCallback(() => {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
        // Clear session storage to prevent old accountId from being used after logout
        sessionStorage.removeItem('accountId');
        setToken(null);
        setUser(null);
    }, []);

    const isAuthenticated = !!token && !!user;

    return (
        <AuthContext.Provider value={{
            user,
            token,
            isAuthenticated,
            isLoading,
            login,
            register,
            logout,
        }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within AuthProvider');
    }
    return context;
}
