export interface MonthlyTotal {
    month: string;
    total: number;
}

export interface MonthlyStatistics {
    median: number;
    trimmedMean: number;
    iqrMean: number;
    weightedMedian: number;
    plainAverage: number;
    monthCount: number;
    monthlyTotals: MonthlyTotal[];
}