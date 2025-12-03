import { Routes, Route } from 'react-router-dom';
import ForecastPage from './ForecastPage';

export default function ForecastModule() {
    return (
        <Routes>
            <Route index element={<ForecastPage />} />
        </Routes>
    );
}
