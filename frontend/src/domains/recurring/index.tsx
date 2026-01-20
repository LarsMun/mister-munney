import { Routes, Route } from 'react-router-dom';
import RecurringPage from './RecurringPage';

export default function RecurringModule() {
    return (
        <Routes>
            <Route index element={<RecurringPage />} />
        </Routes>
    );
}
