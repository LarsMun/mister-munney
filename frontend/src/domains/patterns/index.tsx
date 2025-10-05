// src/domains/patterns/index.tsx
import { Routes, Route } from 'react-router-dom';
import PatternPage from './PatternPage';
import React from 'react';

export default function TransactionsModule() {
    return (
        <Routes>
            <Route index element={<PatternPage />} />
            {/* future detail route */}
        </Routes>
    );
}