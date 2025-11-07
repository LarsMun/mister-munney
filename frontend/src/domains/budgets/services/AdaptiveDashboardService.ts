import api from '../../../lib/axios';
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

/**
 * Fetch active budgets with insights
 */
export async function fetchActiveBudgets(
    months?: number,
    type?: BudgetType,
    startDate?: string,
    endDate?: string,
    accountId?: number
): Promise<ActiveBudget[]> {
    const params = new URLSearchParams();
    if (months !== undefined) params.append('months', months.toString());
    if (type) params.append('type', type);
    if (startDate) params.append('startDate', startDate);
    if (endDate) params.append('endDate', endDate);
    if (accountId !== undefined) params.append('accountId', accountId.toString());

    const response = await api.get(`/budgets/active?${params.toString()}`);
    return response.data;
}

/**
 * Fetch older/inactive budgets
 */
export async function fetchOlderBudgets(
    months?: number,
    type?: BudgetType,
    accountId?: number
): Promise<OlderBudget[]> {
    const params = new URLSearchParams();
    if (months !== undefined) params.append('months', months.toString());
    if (type) params.append('type', type);
    if (accountId !== undefined) params.append('accountId', accountId.toString());

    const response = await api.get(`/budgets/older?${params.toString()}`);
    return response.data;
}

/**
 * Fetch all projects (PROJECT-type budgets)
 */
export async function fetchProjects(
    status?: ProjectStatus,
    accountId?: number
): Promise<ProjectDetails[]> {
    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (accountId !== undefined) params.append('accountId', accountId.toString());

    const response = await api.get(`/budgets?${params.toString()}`);
    return response.data;
}

/**
 * Create a new project
 */
export async function createProject(
    data: CreateProjectDTO
): Promise<ProjectDetails> {
    const response = await api.post('/budgets', data);
    return response.data;
}

/**
 * Update a project
 */
export async function updateProject(
    projectId: number,
    data: UpdateProjectDTO
): Promise<ProjectDetails> {
    const response = await api.patch(`/budgets/${projectId}`, data);
    return response.data;
}

/**
 * Fetch project details with aggregations
 */
export async function fetchProjectDetails(
    projectId: number
): Promise<ProjectDetails> {
    const response = await api.get(`/budgets/${projectId}/details`);
    return response.data;
}

/**
 * Create an external payment for a project
 */
export async function createExternalPayment(
    budgetId: number,
    data: CreateExternalPaymentDTO
): Promise<ExternalPayment> {
    const response = await api.post(`/budgets/${budgetId}/external-payments`, data);
    return response.data;
}

/**
 * Update an external payment
 */
export async function updateExternalPayment(
    paymentId: number,
    data: UpdateExternalPaymentDTO
): Promise<ExternalPayment> {
    const response = await api.patch(`/external-payments/${paymentId}`, data);
    return response.data;
}

/**
 * Delete an external payment
 */
export async function deleteExternalPayment(
    paymentId: number
): Promise<void> {
    await api.delete(`/external-payments/${paymentId}`);
}

/**
 * Remove attachment from external payment (without deleting the payment)
 */
export async function removeExternalPaymentAttachment(
    paymentId: number
): Promise<void> {
    await api.delete(`/external-payments/${paymentId}/attachment`);
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

    const response = await api.post(`/external-payments/${paymentId}/attachment`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });
    return response.data;
}

/**
 * Fetch project entries (merged transactions and external payments)
 */
export async function fetchProjectEntries(
    projectId: number
): Promise<any[]> {
    const response = await api.get(`/budgets/${projectId}/entries`);
    return response.data;
}

/**
 * Fetch external payments for a project
 */
export async function fetchProjectExternalPayments(
    projectId: number
): Promise<ExternalPayment[]> {
    const response = await api.get(`/budgets/${projectId}/external-payments`);
    return response.data;
}

/**
 * Fetch project attachments (general files not tied to payments)
 */
export async function fetchProjectAttachments(
    projectId: number
): Promise<ProjectAttachment[]> {
    const response = await api.get(`/budgets/${projectId}/attachments`);
    return response.data;
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

    const response = await api.post(`/budgets/${projectId}/attachments`, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });
    return response.data;
}

/**
 * Delete a project attachment
 */
export async function deleteProjectAttachment(
    attachmentId: number
): Promise<void> {
    await api.delete(`/attachments/${attachmentId}`);
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
