// frontend/src/domains/forecast/hooks/useForecast.ts

import { useState, useEffect, useCallback } from 'react';
import { useAccount } from '../../../app/context/AccountContext';
import * as ForecastService from '../services/ForecastService';
import type {
    ForecastSummary,
    AvailableItems,
    CreateForecastItem,
    UpdateForecastItem,
    PositionUpdate
} from '../models/Forecast';

export function useForecast() {
    const { accountId } = useAccount();
    const [month, setMonth] = useState<string>(ForecastService.getCurrentMonth());
    const [forecast, setForecast] = useState<ForecastSummary | null>(null);
    const [availableItems, setAvailableItems] = useState<AvailableItems>({ budgets: [], categories: [] });
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const loadForecast = useCallback(async () => {
        if (!accountId) return;

        try {
            setIsLoading(true);
            setError(null);
            const data = await ForecastService.getForecast(accountId, month);
            setForecast(data);
        } catch (err: unknown) {
            const error = err as { response?: { data?: { message?: string; error?: string } } };
            setError(error.response?.data?.message || error.response?.data?.error || 'Er is een fout opgetreden bij het laden van de forecast');
            console.error('Error loading forecast:', err);
        } finally {
            setIsLoading(false);
        }
    }, [accountId, month]);

    const loadAvailableItems = useCallback(async () => {
        if (!accountId) return;

        try {
            const data = await ForecastService.getAvailableItems(accountId);
            setAvailableItems(data);
        } catch (err: unknown) {
            console.error('Error loading available items:', err);
        }
    }, [accountId]);

    const addItem = async (item: CreateForecastItem): Promise<void> => {
        if (!accountId) return;

        await ForecastService.addForecastItem(accountId, item);
        await Promise.all([loadForecast(), loadAvailableItems()]);
    };

    const updateItem = async (itemId: number, data: UpdateForecastItem): Promise<void> => {
        if (!accountId) return;

        await ForecastService.updateForecastItem(accountId, itemId, data);
        await loadForecast();
    };

    const removeItem = async (itemId: number): Promise<void> => {
        if (!accountId) return;

        await ForecastService.deleteForecastItem(accountId, itemId);
        await Promise.all([loadForecast(), loadAvailableItems()]);
    };

    const updatePositions = async (positions: PositionUpdate[]): Promise<void> => {
        if (!accountId) return;

        await ForecastService.updatePositions(accountId, positions);
        await loadForecast();
    };

    const goToPreviousMonth = () => {
        setMonth(ForecastService.getPreviousMonth(month));
    };

    const goToNextMonth = () => {
        setMonth(ForecastService.getNextMonth(month));
    };

    const goToCurrentMonth = () => {
        setMonth(ForecastService.getCurrentMonth());
    };

    const refresh = useCallback(async () => {
        await Promise.all([loadForecast(), loadAvailableItems()]);
    }, [loadForecast, loadAvailableItems]);

    useEffect(() => {
        loadForecast();
        loadAvailableItems();
    }, [loadForecast, loadAvailableItems]);

    return {
        month,
        setMonth,
        forecast,
        availableItems,
        isLoading,
        error,
        addItem,
        updateItem,
        removeItem,
        updatePositions,
        goToPreviousMonth,
        goToNextMonth,
        goToCurrentMonth,
        refresh
    };
}
