/**
 * Utility functions for type-safe error handling
 */

export interface ApiError {
    message: string;
    status?: number;
    code?: string;
    requiresCaptcha?: boolean;
    failedAttempts?: number;
}

/**
 * Type guard to check if an error is an ApiError
 */
export function isApiError(error: unknown): error is ApiError {
    return (
        typeof error === 'object' &&
        error !== null &&
        'message' in error &&
        typeof (error as ApiError).message === 'string'
    );
}

/**
 * Extract error message from unknown error
 */
export function getErrorMessage(error: unknown): string {
    if (isApiError(error)) {
        return error.message;
    }
    if (error instanceof Error) {
        return error.message;
    }
    if (typeof error === 'string') {
        return error;
    }
    return 'Er is een onbekende fout opgetreden';
}

/**
 * Create an ApiError from an unknown error
 */
export function toApiError(error: unknown): ApiError {
    if (isApiError(error)) {
        return error;
    }
    if (error instanceof Error) {
        return { message: error.message };
    }
    if (typeof error === 'string') {
        return { message: error };
    }
    return { message: 'Er is een onbekende fout opgetreden' };
}

/**
 * Check if error requires CAPTCHA
 */
export function requiresCaptcha(error: unknown): boolean {
    return isApiError(error) && error.requiresCaptcha === true;
}

/**
 * Get failed attempts from error
 */
export function getFailedAttempts(error: unknown): number {
    if (isApiError(error) && typeof error.failedAttempts === 'number') {
        return error.failedAttempts;
    }
    return 0;
}
