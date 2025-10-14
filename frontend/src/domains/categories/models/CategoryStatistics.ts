export interface CategoryStatistic {
    categoryId: number;
    categoryName: string;
    categoryColor: string;
    categoryIcon: string;
    totalAmount: number;
    transactionCount: number;
    averagePerTransaction: number;
    averagePerMonth: number;
    monthsWithExpenses: number;
    percentageOfTotal: number;
    // Nieuwe velden voor recente statistieken
    medianLast12Months: number;
    medianAll: number;
    trend: 'increasing' | 'stable' | 'decreasing';
    trendPercentage: number;
}

export interface CategoryStatistics {
    categories: CategoryStatistic[];
    totalSpent: number;
}