import { z } from 'zod';

/**
 * Zod validation schemas for form validation
 * All error messages are in Dutch
 */

// Auth schemas
export const loginSchema = z.object({
    email: z
        .string()
        .min(1, 'E-mailadres is verplicht')
        .email('Ongeldig e-mailadres'),
    password: z
        .string()
        .min(1, 'Wachtwoord is verplicht')
        .min(8, 'Wachtwoord moet minimaal 8 tekens bevatten'),
});

export type LoginFormData = z.infer<typeof loginSchema>;

// Budget schemas
export const createBudgetSchema = z.object({
    name: z
        .string()
        .min(1, 'Naam is verplicht')
        .max(100, 'Naam mag maximaal 100 tekens bevatten'),
    budgetType: z.enum(['EXPENSE', 'INCOME', 'PROJECT'], {
        errorMap: () => ({ message: 'Selecteer een budget type' }),
    }),
    icon: z.string().nullable().optional(),
    categoryIds: z.array(z.number()).optional(),
});

export type CreateBudgetFormData = z.infer<typeof createBudgetSchema>;

// Category schemas
export const categorySchema = z.object({
    name: z
        .string()
        .min(1, 'Naam is verplicht')
        .max(50, 'Naam mag maximaal 50 tekens bevatten'),
    color: z
        .string()
        .regex(/^#[0-9A-Fa-f]{6}$/, 'Ongeldige kleurcode')
        .optional(),
    icon: z.string().nullable().optional(),
});

export type CategoryFormData = z.infer<typeof categorySchema>;

// Pattern schemas
export const patternSchema = z.object({
    name: z
        .string()
        .min(1, 'Naam is verplicht')
        .max(100, 'Naam mag maximaal 100 tekens bevatten'),
    pattern: z
        .string()
        .min(1, 'Patroon is verplicht')
        .max(255, 'Patroon mag maximaal 255 tekens bevatten'),
    categoryId: z.number({
        required_error: 'Selecteer een categorie',
        invalid_type_error: 'Selecteer een categorie',
    }),
    isRegex: z.boolean().optional().default(false),
    priority: z.number().min(0).max(100).optional().default(0),
});

export type PatternFormData = z.infer<typeof patternSchema>;

// Project schemas
export const projectSchema = z.object({
    name: z
        .string()
        .min(1, 'Naam is verplicht')
        .max(100, 'Naam mag maximaal 100 tekens bevatten'),
    description: z
        .string()
        .max(500, 'Beschrijving mag maximaal 500 tekens bevatten')
        .optional(),
    targetAmount: z
        .number({
            required_error: 'Doelbedrag is verplicht',
            invalid_type_error: 'Voer een geldig bedrag in',
        })
        .positive('Bedrag moet positief zijn'),
    targetDate: z
        .string()
        .regex(/^\d{4}-\d{2}-\d{2}$/, 'Ongeldige datum (gebruik YYYY-MM-DD)')
        .optional(),
});

export type ProjectFormData = z.infer<typeof projectSchema>;

// Transaction filter schemas
export const transactionFilterSchema = z.object({
    startDate: z.string().optional(),
    endDate: z.string().optional(),
    categoryId: z.number().optional(),
    minAmount: z.number().optional(),
    maxAmount: z.number().optional(),
    searchText: z.string().max(100).optional(),
});

export type TransactionFilterFormData = z.infer<typeof transactionFilterSchema>;

// Amount input schema (for money fields)
export const amountSchema = z
    .number({
        required_error: 'Bedrag is verplicht',
        invalid_type_error: 'Voer een geldig bedrag in',
    })
    .positive('Bedrag moet positief zijn')
    .multipleOf(0.01, 'Maximaal 2 decimalen toegestaan');

// External payment schema
export const externalPaymentSchema = z.object({
    amount: amountSchema,
    description: z
        .string()
        .min(1, 'Omschrijving is verplicht')
        .max(255, 'Omschrijving mag maximaal 255 tekens bevatten'),
    date: z
        .string()
        .regex(/^\d{4}-\d{2}-\d{2}$/, 'Ongeldige datum'),
});

export type ExternalPaymentFormData = z.infer<typeof externalPaymentSchema>;
