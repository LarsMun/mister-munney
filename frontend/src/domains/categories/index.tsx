import { Routes, Route } from 'react-router-dom';
import CategoriesPage from './CategoriesPage';

export default function CategoriesModule() {
    return (
        <Routes>
            <Route index element={<CategoriesPage />} />
            {/* future detail routes can be added here */}
        </Routes>
    );
}
