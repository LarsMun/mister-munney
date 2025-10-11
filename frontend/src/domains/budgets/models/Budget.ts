// frontend/src/domains/budgets/models/Budget.ts

export interface Budget {
    id: number;
    name: string;
    accountId: number;
    status: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
    statusLabel: string;
    statusColor: string;
    createdAt: string;
    updatedAt: string;
    versions: BudgetVersion[];
    categories: Category[];
    currentMonthlyAmount: number | null;
    currentEffectiveFrom: string | null;
    currentEffectiveUntil: string | null;
}

export interface BudgetVersion {
    id: number;
    monthlyAmount: number;
    effectiveFromMonth: string;
    effectiveUntilMonth: string | null;
    changeReason: string | null;
    createdAt: string;
    isCurrent: boolean; // Added: indicates if this version is currently active
    displayName: string; // Added: formatted display name from backend
}

export interface Category {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    budgetId: number | null;
}

export interface CreateBudget {
    name: string;
    accountId: number;
    monthlyAmount: number;
    effectiveFromMonth: string;
    changeReason?: string;
    categoryIds?: number[];
}

export interface UpdateBudget {
    name?: string;
    status?: 'ACTIVE' | 'INACTIVE' | 'ARCHIVED';
}

// NIEUWE INTERFACE voor budget versie toevoegen
export interface CreateBudgetVersion {
    monthlyAmount: number;
    effectiveFromMonth: string;
    effectiveUntilMonth?: string;
    changeReason?: string;
    categoryIds?: number[];
}

export interface AssignCategories {
    categoryIds: number[];
}

export interface AvailableCategory {
    id: number;
    name: string;
    color: string | null;
    icon: string | null;
    budgetId: number | null;
    isAssigned: boolean;
}

// NIEUWE UTILITY TYPES voor versie validatie
export interface DateOverlapValidation {
    hasOverlap: boolean;
    conflictingVersion?: BudgetVersion;
    suggestedStartDate?: string;
}

// Nieuwe types voor advanced version management
export interface VersionChangePreview {
    newVersion: CreateBudgetVersion;
    affectedVersions: BudgetVersion[];
    actions: VersionAction[];
}

export interface VersionAction {
    type: 'remove' | 'adjust-start' | 'adjust-end' | 'split' | 'create-split-part';
    version: BudgetVersion;
    newStartDate?: Date;
    newEndDate?: Date;
    originalEndDate?: Date | null;
    reason: string;
}

export interface UpdateBudgetVersion {
    monthlyAmount?: number;
    effectiveFromMonth?: string;
    effectiveUntilMonth?: string;
    changeReason?: string;
}

// NIEUWE UTILITY TYPES voor update vs add logica
export interface UpdateVsAddDecision {
    useUpdate: boolean;
    targetVersionId?: number;
    reason: string;
    warningMessage?: string;
}

// Uitbreiding van VersionChangePreview voor updates
export interface VersionUpdatePreview {
    originalVersion: BudgetVersion;
    updatedVersion: Partial<BudgetVersion>;
    affectedVersions: BudgetVersion[];
    actions: VersionAction[];
    warnings: string[];
}
