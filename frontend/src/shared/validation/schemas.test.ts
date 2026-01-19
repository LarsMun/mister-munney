import { describe, it, expect } from 'vitest'
import {
  loginSchema,
  createBudgetSchema,
  categorySchema,
  patternSchema,
  projectSchema,
  transactionFilterSchema,
  amountSchema,
  externalPaymentSchema,
} from './schemas'

describe('Validation Schemas', () => {
  describe('loginSchema', () => {
    it('validates correct login data', () => {
      const result = loginSchema.safeParse({
        email: 'user@example.com',
        password: 'password123',
      })
      expect(result.success).toBe(true)
    })

    it('rejects empty email', () => {
      const result = loginSchema.safeParse({
        email: '',
        password: 'password123',
      })
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues[0].message).toBe('E-mailadres is verplicht')
      }
    })

    it('rejects invalid email format', () => {
      const result = loginSchema.safeParse({
        email: 'invalid-email',
        password: 'password123',
      })
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues[0].message).toBe('Ongeldig e-mailadres')
      }
    })

    it('rejects short password', () => {
      const result = loginSchema.safeParse({
        email: 'user@example.com',
        password: 'short',
      })
      expect(result.success).toBe(false)
      if (!result.success) {
        expect(result.error.issues[0].message).toBe('Wachtwoord moet minimaal 8 tekens bevatten')
      }
    })
  })

  describe('createBudgetSchema', () => {
    it('validates correct budget data', () => {
      const result = createBudgetSchema.safeParse({
        name: 'Boodschappen',
        budgetType: 'EXPENSE',
      })
      expect(result.success).toBe(true)
    })

    it('accepts all budget types', () => {
      expect(createBudgetSchema.safeParse({ name: 'Test', budgetType: 'EXPENSE' }).success).toBe(true)
      expect(createBudgetSchema.safeParse({ name: 'Test', budgetType: 'INCOME' }).success).toBe(true)
      expect(createBudgetSchema.safeParse({ name: 'Test', budgetType: 'PROJECT' }).success).toBe(true)
    })

    it('rejects empty name', () => {
      const result = createBudgetSchema.safeParse({
        name: '',
        budgetType: 'EXPENSE',
      })
      expect(result.success).toBe(false)
    })

    it('rejects name over 100 characters', () => {
      const result = createBudgetSchema.safeParse({
        name: 'a'.repeat(101),
        budgetType: 'EXPENSE',
      })
      expect(result.success).toBe(false)
    })

    it('rejects invalid budget type', () => {
      const result = createBudgetSchema.safeParse({
        name: 'Test',
        budgetType: 'INVALID',
      })
      expect(result.success).toBe(false)
    })
  })

  describe('categorySchema', () => {
    it('validates correct category data', () => {
      const result = categorySchema.safeParse({
        name: 'Groceries',
      })
      expect(result.success).toBe(true)
    })

    it('validates category with color', () => {
      const result = categorySchema.safeParse({
        name: 'Groceries',
        color: '#FF5733',
      })
      expect(result.success).toBe(true)
    })

    it('rejects invalid color format', () => {
      const result = categorySchema.safeParse({
        name: 'Test',
        color: 'red',
      })
      expect(result.success).toBe(false)
    })

    it('rejects name over 50 characters', () => {
      const result = categorySchema.safeParse({
        name: 'a'.repeat(51),
      })
      expect(result.success).toBe(false)
    })
  })

  describe('patternSchema', () => {
    it('validates correct pattern data', () => {
      const result = patternSchema.safeParse({
        name: 'Albert Heijn',
        pattern: 'albert heijn',
        categoryId: 1,
      })
      expect(result.success).toBe(true)
    })

    it('accepts optional isRegex flag', () => {
      const result = patternSchema.safeParse({
        name: 'Supermarket',
        pattern: 'albert|jumbo|lidl',
        categoryId: 1,
        isRegex: true,
      })
      expect(result.success).toBe(true)
    })

    it('rejects missing categoryId', () => {
      const result = patternSchema.safeParse({
        name: 'Test',
        pattern: 'test',
      })
      expect(result.success).toBe(false)
    })

    it('rejects pattern over 255 characters', () => {
      const result = patternSchema.safeParse({
        name: 'Test',
        pattern: 'a'.repeat(256),
        categoryId: 1,
      })
      expect(result.success).toBe(false)
    })
  })

  describe('projectSchema', () => {
    it('validates correct project data', () => {
      const result = projectSchema.safeParse({
        name: 'Vacation',
        targetAmount: 2000,
      })
      expect(result.success).toBe(true)
    })

    it('accepts optional targetDate', () => {
      const result = projectSchema.safeParse({
        name: 'Vacation',
        targetAmount: 2000,
        targetDate: '2025-12-31',
      })
      expect(result.success).toBe(true)
    })

    it('rejects negative targetAmount', () => {
      const result = projectSchema.safeParse({
        name: 'Test',
        targetAmount: -100,
      })
      expect(result.success).toBe(false)
    })

    it('rejects invalid date format', () => {
      const result = projectSchema.safeParse({
        name: 'Test',
        targetAmount: 1000,
        targetDate: '31-12-2025',
      })
      expect(result.success).toBe(false)
    })
  })

  describe('transactionFilterSchema', () => {
    it('validates empty filter', () => {
      const result = transactionFilterSchema.safeParse({})
      expect(result.success).toBe(true)
    })

    it('validates filter with all fields', () => {
      const result = transactionFilterSchema.safeParse({
        startDate: '2024-01-01',
        endDate: '2024-12-31',
        categoryId: 5,
        minAmount: 0,
        maxAmount: 1000,
        searchText: 'groceries',
      })
      expect(result.success).toBe(true)
    })

    it('rejects searchText over 100 characters', () => {
      const result = transactionFilterSchema.safeParse({
        searchText: 'a'.repeat(101),
      })
      expect(result.success).toBe(false)
    })
  })

  describe('amountSchema', () => {
    it('validates positive amounts', () => {
      const result = amountSchema.safeParse(100.50)
      expect(result.success).toBe(true)
    })

    it('rejects zero', () => {
      const result = amountSchema.safeParse(0)
      expect(result.success).toBe(false)
    })

    it('rejects negative amounts', () => {
      const result = amountSchema.safeParse(-50)
      expect(result.success).toBe(false)
    })

    it('allows up to 2 decimal places', () => {
      expect(amountSchema.safeParse(100.01).success).toBe(true)
      expect(amountSchema.safeParse(100.99).success).toBe(true)
    })

    it('rejects more than 2 decimal places', () => {
      const result = amountSchema.safeParse(100.001)
      expect(result.success).toBe(false)
    })
  })

  describe('externalPaymentSchema', () => {
    it('validates correct payment data', () => {
      const result = externalPaymentSchema.safeParse({
        amount: 50.00,
        description: 'Cash payment',
        date: '2024-01-15',
      })
      expect(result.success).toBe(true)
    })

    it('rejects empty description', () => {
      const result = externalPaymentSchema.safeParse({
        amount: 50.00,
        description: '',
        date: '2024-01-15',
      })
      expect(result.success).toBe(false)
    })

    it('rejects invalid date format', () => {
      const result = externalPaymentSchema.safeParse({
        amount: 50.00,
        description: 'Test',
        date: '15-01-2024',
      })
      expect(result.success).toBe(false)
    })
  })
})
