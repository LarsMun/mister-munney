// Main.tsx

import React from 'react';
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from './shared/contexts/AuthContext';
import { AccountProvider } from './app/context/AccountContext';
import { FeatureFlagProvider } from './shared/contexts/FeatureFlagContext';
import ErrorBoundary from './shared/components/ErrorBoundary';

createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <ErrorBoundary>
            <AuthProvider>
                <FeatureFlagProvider>
                    <AccountProvider>
                        <App />
                    </AccountProvider>
                </FeatureFlagProvider>
            </AuthProvider>
        </ErrorBoundary>
    </React.StrictMode>
);


