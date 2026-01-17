import { Routes, Route } from 'react-router-dom';
import DashboardPage from './DashboardPage';
import SankeyFlowPage from './SankeyFlowPage';

export default function DashboardModule() {
    return (
        <Routes>
            <Route path="/" element={<DashboardPage />} />
            <Route path="/flow" element={<SankeyFlowPage />} />
        </Routes>
    );
}
