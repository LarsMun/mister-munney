// Main.tsx

import React from 'react';
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { AuthProvider } from './shared/contexts/AuthContext';
import { AccountProvider } from './app/context/AccountContext';
import { FeatureFlagProvider } from './shared/contexts/FeatureFlagContext';

createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AuthProvider>
            <FeatureFlagProvider>
                <AccountProvider>
                    <App />
                </AccountProvider>
            </FeatureFlagProvider>
        </AuthProvider>
    </React.StrictMode>
);


