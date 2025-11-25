export type Transaction = {
    id: number;
    hash: string;
    date: string;
    description: string;
    accountId: number;
    counterpartyAccount: string | null;
    transactionCode: string;
    transactionType: "credit" | "debit";
    amount: number;
    mutationType: string;
    notes: string;
    balanceAfter: number;
    tag: string | null;
    categoryId: number | null;
};