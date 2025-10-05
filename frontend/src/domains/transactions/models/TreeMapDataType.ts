// src/domains/transactions/models/TreeMapDataType.ts

export interface TreeMapCategoryData {
    categoryId: number;
    categoryName: string;
    transactionCount: number;
    totalAmount: number;
    categoryColor: string;
    categoryIcon: string;
    percentageOfTotal: number;
}

export interface TreeMapDataType {
    debit: TreeMapCategoryData[];
    credit: TreeMapCategoryData[];
}