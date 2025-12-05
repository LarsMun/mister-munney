import { useState, useCallback } from 'react';
import { z, ZodError, ZodSchema } from 'zod';

export interface FormErrors {
    [key: string]: string | undefined;
}

export interface UseFormValidationResult<T> {
    errors: FormErrors;
    validate: (data: unknown) => data is T;
    validateField: (field: keyof T, value: unknown) => string | undefined;
    clearErrors: () => void;
    clearFieldError: (field: keyof T) => void;
    setFieldError: (field: keyof T, message: string) => void;
    hasErrors: boolean;
}

/**
 * Custom hook for form validation using Zod schemas
 *
 * @example
 * const { errors, validate, validateField, clearErrors } = useFormValidation(loginSchema);
 *
 * const handleSubmit = (data: unknown) => {
 *   if (validate(data)) {
 *     // data is now typed as LoginFormData
 *     await login(data.email, data.password);
 *   }
 * };
 *
 * const handleBlur = (field: keyof LoginFormData, value: string) => {
 *   validateField(field, value);
 * };
 */
export function useFormValidation<T>(schema: ZodSchema<T>): UseFormValidationResult<T> {
    const [errors, setErrors] = useState<FormErrors>({});

    const validate = useCallback((data: unknown): data is T => {
        try {
            schema.parse(data);
            setErrors({});
            return true;
        } catch (error) {
            if (error instanceof ZodError) {
                const newErrors: FormErrors = {};
                error.errors.forEach((err) => {
                    const path = err.path.join('.');
                    if (path && !newErrors[path]) {
                        newErrors[path] = err.message;
                    }
                });
                setErrors(newErrors);
            }
            return false;
        }
    }, [schema]);

    const validateField = useCallback((field: keyof T, value: unknown): string | undefined => {
        try {
            // Create a partial schema for just this field
            const fieldSchema = (schema as z.ZodObject<z.ZodRawShape>).shape[field as string];
            if (fieldSchema) {
                fieldSchema.parse(value);
                setErrors((prev) => {
                    const newErrors = { ...prev };
                    delete newErrors[field as string];
                    return newErrors;
                });
                return undefined;
            }
        } catch (error) {
            if (error instanceof ZodError && error.errors[0]) {
                const message = error.errors[0].message;
                setErrors((prev) => ({
                    ...prev,
                    [field as string]: message,
                }));
                return message;
            }
        }
        return undefined;
    }, [schema]);

    const clearErrors = useCallback(() => {
        setErrors({});
    }, []);

    const clearFieldError = useCallback((field: keyof T) => {
        setErrors((prev) => {
            const newErrors = { ...prev };
            delete newErrors[field as string];
            return newErrors;
        });
    }, []);

    const setFieldError = useCallback((field: keyof T, message: string) => {
        setErrors((prev) => ({
            ...prev,
            [field as string]: message,
        }));
    }, []);

    return {
        errors,
        validate,
        validateField,
        clearErrors,
        clearFieldError,
        setFieldError,
        hasErrors: Object.keys(errors).length > 0,
    };
}

/**
 * Helper to safely parse a form value to a number
 */
export function parseNumberInput(value: string): number | undefined {
    if (!value || value.trim() === '') return undefined;
    const parsed = parseFloat(value.replace(',', '.'));
    return isNaN(parsed) ? undefined : parsed;
}

/**
 * Format validation errors for display
 */
export function formatValidationError(error: ZodError): string {
    return error.errors.map(e => e.message).join(', ');
}
