// src/domains/transactions/index.tsx
import { Routes, Route } from 'react-router-dom';
import TransactionPage from './TransactionPage';

export default function TransactionsModule() {
    return (
        <Routes>
            <Route index element={<TransactionPage />} />
            {/* future detail route */}
        </Routes>
    );
}