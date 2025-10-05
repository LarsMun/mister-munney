export type SummaryType = {
    total: number;
    total_debit: string;
    total_credit: string;
    net_total: string;
    start_balance: string;
    end_balance: string;
    daily: {
        date: string;
        value: number;
        debitTotal: number;
        creditTotal: number;
    }[];
};