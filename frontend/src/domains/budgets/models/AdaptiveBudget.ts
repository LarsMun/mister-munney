export type BudgetType = 'EXPENSE' | 'INCOME' | 'PROJECT';
export type ProjectStatus = 'ACTIVE' | 'COMPLETED';
export type PayerSource = 'SELF' | 'MORTGAGE_DEPOT' | 'INSURER' | 'OTHER';

export interface BudgetInsight {
    budgetId: number;
    budgetName: string;
    current: string;
    normal: string;
    average: string;
    previousPeriod: string | null;
    previousPeriodLabel: string;
    lastYear: string | null;
    sparkline: number[];
    periodType: 'month' | 'quarter' | 'halfYear' | 'year' | 'custom';
}

export interface ActiveBudget {
    id: number;
    name: string;
    budgetType: BudgetType;
    description?: string;
    startDate?: string;
    endDate?: string;
    status?: ProjectStatus;
    insight?: BudgetInsight;
    categoryCount: number;
}

export interface OlderBudget {
    id: number;
    name: string;
    budgetType: BudgetType;
    categoryCount: number;
}

export interface ProjectTotals {
    trackedDebit: string;
    trackedCredit: string;
    tracked: string;
    external: string;
    total: string;
    categoryBreakdown: CategoryBreakdown[];
    duration: ProjectDuration | null;
}

export interface ProjectDuration {
    startDate: string;
    endDate: string;
    days: number;
    months: number;
}

export interface CategoryBreakdown {
    categoryId: number;
    categoryName: string;
    total: string;
}

export interface MonthlyBar {
    month: string;
    tracked: number;
    external: number;
    total: number;
}

export interface CumulativeLine {
    month: string;
    cumulative: number;
}

export interface ProjectTimeSeries {
    monthlyBars: MonthlyBar[];
    cumulativeLine: CumulativeLine[];
}

export interface ProjectDetails {
    id: number;
    name: string;
    description?: string;
    durationMonths: number;
    status?: ProjectStatus;
    totals: ProjectTotals;
    timeSeries: ProjectTimeSeries;
    categoryCount: number;
}

export interface ExternalPayment {
    id: number;
    budgetId: number;
    amount: string;
    paidOn: string;
    payerSource: PayerSource;
    note: string;
    attachmentUrl?: string;
    createdAt: string;
    updatedAt?: string;
}

export interface CreateProjectDTO {
    name: string;
    description?: string;
    accountId: number;
    durationMonths?: number;
}

export interface UpdateProjectDTO {
    name?: string;
    description?: string;
    durationMonths?: number;
}

export interface CreateExternalPaymentDTO {
    amount: number;
    paidOn: string;
    payerSource: PayerSource;
    note: string;
}

export interface UpdateExternalPaymentDTO {
    amount?: number;
    paidOn?: string;
    payerSource?: PayerSource;
    note?: string;
}
