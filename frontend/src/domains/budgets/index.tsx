import { Routes, Route } from 'react-router-dom';
import BudgetsPage from './BudgetsPage';
import ProjectDetailPage from './ProjectDetailPage';

export default function BudgetsModule() {
    return (
        <Routes>
            <Route index element={<BudgetsPage />} />
            <Route path="projects/:projectId" element={<ProjectDetailPage />} />
        </Routes>
    );
}