// frontend/src/domains/forecast/services/ForecastService.ts

import api from '../../../lib/axios';
import type {
    ForecastSummary,
    AvailableItems,
    CreateForecastItem,
    UpdateForecastItem,
    PositionUpdate,
    ForecastItem
} from '../models/Forecast';

export async function getForecast(accountId: number, month: string): Promise<ForecastSummary> {
    const res = await api.get(`/account/${accountId}/forecast`, {
        params: { month }
    });
    return res.data;
}

export async function getAvailableItems(accountId: number): Promise<AvailableItems> {
    const res = await api.get(`/account/${accountId}/forecast/available`);
    return res.data;
}

export async function addForecastItem(
    accountId: number,
    item: CreateForecastItem
): Promise<ForecastItem> {
    const res = await api.post(`/account/${accountId}/forecast/items`, item);
    return res.data;
}

export async function updateForecastItem(
    accountId: number,
    itemId: number,
    data: UpdateForecastItem
): Promise<ForecastItem> {
    const res = await api.put(`/account/${accountId}/forecast/items/${itemId}`, data);
    return res.data;
}

export async function deleteForecastItem(
    accountId: number,
    itemId: number
): Promise<void> {
    await api.delete(`/account/${accountId}/forecast/items/${itemId}`);
}

export async function updatePositions(
    accountId: number,
    positions: PositionUpdate[]
): Promise<void> {
    await api.put(`/account/${accountId}/forecast/positions`, positions);
}

// Utility functions
export function getCurrentMonth(): string {
    return new Date().toISOString().substring(0, 7);
}

export function formatMonthDisplay(month: string): string {
    const [year, monthNum] = month.split('-');
    const monthNames = [
        'Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni',
        'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'
    ];
    return `${monthNames[parseInt(monthNum, 10) - 1]} ${year}`;
}

export function getPreviousMonth(month: string): string {
    const [year, monthNum] = month.split('-').map(Number);
    const date = new Date(year, monthNum - 2, 1); // -2 because months are 0-indexed
    return date.toISOString().substring(0, 7);
}

export function getNextMonth(month: string): string {
    const [year, monthNum] = month.split('-').map(Number);
    const date = new Date(year, monthNum, 1); // monthNum is already the next month (0-indexed)
    return date.toISOString().substring(0, 7);
}
