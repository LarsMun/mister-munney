// src/domains/transactions/index.tsx
import { Routes, Route } from 'react-router-dom';
import TransactionPage from './TransactionPage';
import React from 'react';

export default function TransactionsModule() {
    return (
        <Routes>
            <Route index element={<TransactionPage />} />
            {/* future detail route */}
        </Routes>
    );
}