import { API_URL } from '../../../lib/api';
import {
    ActiveBudget,
    OlderBudget,
    ProjectDetails,
    CreateProjectDTO,
    UpdateProjectDTO,
    ExternalPayment,
    CreateExternalPaymentDTO,
    UpdateExternalPaymentDTO,
    BudgetType,
    ProjectStatus
} from '../models/AdaptiveBudget';

const prefix = import.meta.env.VITE_API_URL || 'http://localhost:8787/api';

/**
 * Fetch active budgets with insights
 */
export async function fetchActiveBudgets(
    months?: number,
    type?: BudgetType,
    startDate?: string,
    endDate?: string
): Promise<ActiveBudget[]> {
    const params = new URLSearchParams();
    if (months !== undefined) params.append('months', months.toString());
    if (type) params.append('type', type);
    if (startDate) params.append('startDate', startDate);
    if (endDate) params.append('endDate', endDate);

    const response = await fetch(
        `${prefix}/budgets/active?${params.toString()}`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch active budgets');
    }

    return await response.json();
}

/**
 * Fetch older/inactive budgets
 */
export async function fetchOlderBudgets(
    months?: number,
    type?: BudgetType
): Promise<OlderBudget[]> {
    const params = new URLSearchParams();
    if (months !== undefined) params.append('months', months.toString());
    if (type) params.append('type', type);

    const response = await fetch(
        `${prefix}/budgets/older?${params.toString()}`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch older budgets');
    }

    return await response.json();
}

/**
 * Fetch all projects (PROJECT-type budgets)
 */
export async function fetchProjects(
    status?: ProjectStatus
): Promise<ProjectDetails[]> {
    const params = new URLSearchParams();
    if (status) params.append('status', status);

    const response = await fetch(
        `${prefix}/budgets?${params.toString()}`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch projects');
    }

    return await response.json();
}

/**
 * Create a new project
 */
export async function createProject(
    data: CreateProjectDTO
): Promise<ProjectDetails> {
    const response = await fetch(`${prefix}/budgets`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to create project');
    }

    return await response.json();
}

/**
 * Update a project
 */
export async function updateProject(
    projectId: number,
    data: UpdateProjectDTO
): Promise<ProjectDetails> {
    const response = await fetch(`${prefix}/budgets/${projectId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data),
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to update project');
    }

    return await response.json();
}

/**
 * Fetch project details with aggregations
 */
export async function fetchProjectDetails(
    projectId: number
): Promise<ProjectDetails> {
    const response = await fetch(
        `${prefix}/budgets/${projectId}/details`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch project details');
    }

    return await response.json();
}

/**
 * Create an external payment for a project
 */
export async function createExternalPayment(
    budgetId: number,
    data: CreateExternalPaymentDTO
): Promise<ExternalPayment> {
    const response = await fetch(
        `${prefix}/budgets/${budgetId}/external-payments`,
        {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        }
    );

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to create external payment');
    }

    return await response.json();
}

/**
 * Update an external payment
 */
export async function updateExternalPayment(
    paymentId: number,
    data: UpdateExternalPaymentDTO
): Promise<ExternalPayment> {
    const response = await fetch(
        `${prefix}/external-payments/${paymentId}`,
        {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data),
        }
    );

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.message || 'Failed to update external payment');
    }

    return await response.json();
}

/**
 * Delete an external payment
 */
export async function deleteExternalPayment(
    paymentId: number
): Promise<void> {
    const response = await fetch(
        `${prefix}/external-payments/${paymentId}`,
        {
            method: 'DELETE',
        }
    );

    if (!response.ok) {
        throw new Error('Failed to delete external payment');
    }
}

/**
 * Remove attachment from external payment (without deleting the payment)
 */
export async function removeExternalPaymentAttachment(
    paymentId: number
): Promise<void> {
    const response = await fetch(
        `${prefix}/external-payments/${paymentId}/attachment`,
        {
            method: 'DELETE',
        }
    );

    if (!response.ok) {
        throw new Error('Failed to remove attachment');
    }
}

/**
 * Upload attachment for an external payment
 */
export async function uploadExternalPaymentAttachment(
    paymentId: number,
    file: File
): Promise<{ attachmentUrl: string; message: string }> {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch(
        `${prefix}/external-payments/${paymentId}/attachment`,
        {
            method: 'POST',
            body: formData,
        }
    );

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to upload attachment');
    }

    return await response.json();
}

/**
 * Fetch project entries (merged transactions and external payments)
 */
export async function fetchProjectEntries(
    projectId: number
): Promise<any[]> {
    const response = await fetch(
        `${prefix}/budgets/${projectId}/entries`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch project entries');
    }

    return await response.json();
}

/**
 * Fetch external payments for a project
 */
export async function fetchProjectExternalPayments(
    projectId: number
): Promise<ExternalPayment[]> {
    const response = await fetch(
        `${prefix}/budgets/${projectId}/external-payments`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch external payments');
    }

    return await response.json();
}

/**
 * Fetch project attachments (general files not tied to payments)
 */
export async function fetchProjectAttachments(
    projectId: number
): Promise<ProjectAttachment[]> {
    const response = await fetch(
        `${prefix}/budgets/${projectId}/attachments`
    );

    if (!response.ok) {
        throw new Error('Failed to fetch project attachments');
    }

    return await response.json();
}

/**
 * Upload a project attachment
 */
export async function uploadProjectAttachment(
    projectId: number,
    file: File,
    title: string,
    description?: string,
    category?: string
): Promise<ProjectAttachment> {
    const formData = new FormData();
    formData.append('file', file);
    formData.append('title', title);
    if (description) formData.append('description', description);
    if (category) formData.append('category', category);

    const response = await fetch(
        `${prefix}/budgets/${projectId}/attachments`,
        {
            method: 'POST',
            body: formData,
        }
    );

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || 'Failed to upload attachment');
    }

    return await response.json();
}

/**
 * Delete a project attachment
 */
export async function deleteProjectAttachment(
    attachmentId: number
): Promise<void> {
    const response = await fetch(
        `${prefix}/attachments/${attachmentId}`,
        {
            method: 'DELETE',
        }
    );

    if (!response.ok) {
        throw new Error('Failed to delete attachment');
    }
}

export interface ProjectAttachment {
    id: number;
    title: string;
    description: string | null;
    fileUrl: string;
    originalFilename: string;
    category: string | null;
    uploadedAt: string;
}
