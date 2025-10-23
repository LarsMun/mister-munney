// frontend/src/domains/budgets/models/CategoryBreakdown.ts

export interface CategoryBreakdown {
    categoryId: number;
    categoryName: string;
    categoryColor: string;
    spentAmount: number;
    transactionCount: number;
}
