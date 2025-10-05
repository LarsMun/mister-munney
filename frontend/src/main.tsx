// Main.tsx

import React from 'react';
import { createRoot } from 'react-dom/client'
import './index.css'
import App from './App.tsx'
import { AccountProvider } from './app/context/AccountContext';

createRoot(document.getElementById('root')!).render(
    <React.StrictMode>
        <AccountProvider>
            <App />
        </AccountProvider>
    </React.StrictMode>
);


