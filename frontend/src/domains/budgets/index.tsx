import { Routes, Route } from 'react-router-dom';
import BudgetsPage from './BudgetsPage';
import React from 'react';

export default function BudgetsModule() {
    return (
        <Routes>
            <Route index element={<BudgetsPage />} />
            {/* future detail routes */}
        </Routes>
    );
}