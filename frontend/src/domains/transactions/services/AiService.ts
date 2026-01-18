import api from "../../../lib/axios";

export interface AiCategorySuggestion {
    transactionId: number;
    suggestedCategoryId: number | null;
    confidence: number;
    reasoning: string;
}

export interface AiSuggestionsResponse {
    suggestions: AiCategorySuggestion[];
    total: number;
    message?: string;
}

export interface BulkAssignmentRequest {
    assignments: Array<{
        transactionId: number;
        categoryId: number;
    }>;
}

export interface BulkAssignmentResponse {
    success: number;
    failed: number;
    errors: string[];
}

export async function getAiCategorySuggestions(
    accountId: number,
    transactionIds?: number[],
    limit: number = 50
): Promise<AiSuggestionsResponse> {
    const response = await api.post(
        `/account/${accountId}/transactions/ai-suggest-categories`,
        {
            limit,
            transactionIds: transactionIds?.slice(0, limit) // Only send up to limit
        }
    );
    return response.data;
}

export async function bulkAssignCategories(
    accountId: number,
    assignments: BulkAssignmentRequest
): Promise<BulkAssignmentResponse> {
    const response = await api.post(
        `/account/${accountId}/transactions/bulk-assign-categories`,
        assignments
    );
    return response.data;
}
