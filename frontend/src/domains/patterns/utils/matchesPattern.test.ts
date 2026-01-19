import { describe, it, expect } from 'vitest'
import { matchesPattern, getPatternMatchResult } from './matchesPattern'
import type { PatternInput } from '../models/PatternInput'
import type { Transaction } from '../../transactions/models/Transaction'

// Helper to create a test transaction
function createTransaction(overrides: Partial<Transaction> = {}): Transaction {
  return {
    id: 1,
    hash: 'test-hash',
    date: '2024-01-15',
    description: 'Albert Heijn Betaling',
    accountId: 1,
    counterpartyAccount: 'NL12ABCD1234567890',
    transactionCode: 'GT',
    transactionType: 'debit',
    amount: 50.00,
    mutationType: 'Betaalautomaat',
    notes: 'Weekly groceries',
    balanceAfter: 1000.00,
    tag: null,
    ...overrides,
  }
}

// Helper to create a pattern
function createPattern(overrides: Partial<PatternInput> = {}): PatternInput {
  return {
    accountId: 1,
    ...overrides,
  }
}

describe('matchesPattern', () => {
  describe('description matching', () => {
    it('matches with LIKE (default) - contains search', () => {
      const t = createTransaction({ description: 'Albert Heijn Amsterdam' })
      const pattern = createPattern({ description: 'albert heijn' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches case-insensitively', () => {
      const t = createTransaction({ description: 'ALBERT HEIJN' })
      const pattern = createPattern({ description: 'albert heijn' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('does not match if description is not contained', () => {
      const t = createTransaction({ description: 'Jumbo Betaling' })
      const pattern = createPattern({ description: 'albert heijn' })
      expect(matchesPattern(t, pattern)).toBe(false)
    })

    it('matches EXACT type only for exact matches', () => {
      const t = createTransaction({ description: 'albert heijn' })
      const pattern = createPattern({ description: 'albert heijn', matchTypeDescription: 'EXACT' })
      expect(matchesPattern(t, pattern)).toBe(true)

      const t2 = createTransaction({ description: 'Albert Heijn Amsterdam' })
      expect(matchesPattern(t2, pattern)).toBe(false)
    })

    it('matches empty pattern to any description', () => {
      const t = createTransaction({ description: 'Any description' })
      const pattern = createPattern({ description: '' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches undefined pattern to any description', () => {
      const t = createTransaction({ description: 'Any description' })
      const pattern = createPattern({})
      expect(matchesPattern(t, pattern)).toBe(true)
    })
  })

  describe('notes matching', () => {
    it('matches notes with LIKE (default)', () => {
      const t = createTransaction({ notes: 'Weekly groceries shopping' })
      const pattern = createPattern({ notes: 'groceries' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches notes with EXACT', () => {
      const t = createTransaction({ notes: 'groceries' })
      const pattern = createPattern({ notes: 'groceries', matchTypeNotes: 'EXACT' })
      expect(matchesPattern(t, pattern)).toBe(true)

      const t2 = createTransaction({ notes: 'Weekly groceries' })
      expect(matchesPattern(t2, pattern)).toBe(false)
    })
  })

  describe('transaction type matching', () => {
    it('matches debit transactions', () => {
      const t = createTransaction({ transactionType: 'debit' })
      const pattern = createPattern({ transactionType: 'debit' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches credit transactions', () => {
      const t = createTransaction({ transactionType: 'credit' })
      const pattern = createPattern({ transactionType: 'credit' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches both transaction types', () => {
      const pattern = createPattern({ transactionType: 'both' })
      expect(matchesPattern(createTransaction({ transactionType: 'debit' }), pattern)).toBe(true)
      expect(matchesPattern(createTransaction({ transactionType: 'credit' }), pattern)).toBe(true)
    })

    it('matches when transactionType is not specified', () => {
      const pattern = createPattern({})
      expect(matchesPattern(createTransaction({ transactionType: 'debit' }), pattern)).toBe(true)
      expect(matchesPattern(createTransaction({ transactionType: 'credit' }), pattern)).toBe(true)
    })

    it('does not match wrong transaction type', () => {
      const t = createTransaction({ transactionType: 'credit' })
      const pattern = createPattern({ transactionType: 'debit' })
      expect(matchesPattern(t, pattern)).toBe(false)
    })
  })

  describe('amount matching', () => {
    it('matches within amount range', () => {
      const t = createTransaction({ amount: 50 })
      const pattern = createPattern({ minAmount: 0, maxAmount: 100 })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches at minimum boundary', () => {
      const t = createTransaction({ amount: 50 })
      const pattern = createPattern({ minAmount: 50, maxAmount: 100 })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches at maximum boundary', () => {
      const t = createTransaction({ amount: 100 })
      const pattern = createPattern({ minAmount: 50, maxAmount: 100 })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('does not match below minimum', () => {
      const t = createTransaction({ amount: 25 })
      const pattern = createPattern({ minAmount: 50, maxAmount: 100 })
      expect(matchesPattern(t, pattern)).toBe(false)
    })

    it('does not match above maximum', () => {
      const t = createTransaction({ amount: 150 })
      const pattern = createPattern({ minAmount: 50, maxAmount: 100 })
      expect(matchesPattern(t, pattern)).toBe(false)
    })

    it('matches with only minAmount set', () => {
      const t = createTransaction({ amount: 100 })
      const pattern = createPattern({ minAmount: 50 })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches with only maxAmount set', () => {
      const t = createTransaction({ amount: 50 })
      const pattern = createPattern({ maxAmount: 100 })
      expect(matchesPattern(t, pattern)).toBe(true)
    })
  })

  describe('date matching', () => {
    it('matches within date range', () => {
      const t = createTransaction({ date: '2024-01-15' })
      const pattern = createPattern({ startDate: '2024-01-01', endDate: '2024-01-31' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches at start date boundary', () => {
      const t = createTransaction({ date: '2024-01-01' })
      const pattern = createPattern({ startDate: '2024-01-01', endDate: '2024-01-31' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('matches at end date boundary', () => {
      const t = createTransaction({ date: '2024-01-31' })
      const pattern = createPattern({ startDate: '2024-01-01', endDate: '2024-01-31' })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('does not match before start date', () => {
      const t = createTransaction({ date: '2023-12-31' })
      const pattern = createPattern({ startDate: '2024-01-01', endDate: '2024-01-31' })
      expect(matchesPattern(t, pattern)).toBe(false)
    })

    it('does not match after end date', () => {
      const t = createTransaction({ date: '2024-02-01' })
      const pattern = createPattern({ startDate: '2024-01-01', endDate: '2024-01-31' })
      expect(matchesPattern(t, pattern)).toBe(false)
    })
  })

  describe('combined matching', () => {
    it('matches when all criteria match', () => {
      const t = createTransaction({
        description: 'Albert Heijn',
        transactionType: 'debit',
        amount: 50,
        date: '2024-01-15',
      })
      const pattern = createPattern({
        description: 'albert',
        transactionType: 'debit',
        minAmount: 0,
        maxAmount: 100,
        startDate: '2024-01-01',
        endDate: '2024-01-31',
      })
      expect(matchesPattern(t, pattern)).toBe(true)
    })

    it('does not match when one criterion fails', () => {
      const t = createTransaction({
        description: 'Albert Heijn',
        transactionType: 'credit', // wrong type
        amount: 50,
      })
      const pattern = createPattern({
        description: 'albert',
        transactionType: 'debit',
      })
      expect(matchesPattern(t, pattern)).toBe(false)
    })
  })
})

describe('getPatternMatchResult', () => {
  it('returns correct match counts', () => {
    const transactions: Transaction[] = [
      createTransaction({ id: 1, description: 'Albert Heijn', category: undefined }),
      createTransaction({ id: 2, description: 'Albert Heijn', category: { id: 5, name: 'Groceries' } }),
      createTransaction({ id: 3, description: 'Albert Heijn', category: { id: 10, name: 'Other' } }),
      createTransaction({ id: 4, description: 'Jumbo', category: undefined }),
    ]

    const pattern = createPattern({ description: 'albert', categoryId: 5 })
    const result = getPatternMatchResult(transactions, pattern)

    expect(result.total).toBe(3)
    expect(result.unassigned).toBe(1)
    expect(result.assignedSame).toBe(1)
    expect(result.assignedOther).toBe(1)
    expect(result.matched.length).toBe(3)
  })

  it('returns empty results when no matches', () => {
    const transactions: Transaction[] = [
      createTransaction({ description: 'Jumbo' }),
    ]

    const pattern = createPattern({ description: 'albert' })
    const result = getPatternMatchResult(transactions, pattern)

    expect(result.total).toBe(0)
    expect(result.matched.length).toBe(0)
  })

  it('filters strict mode to exclude same category', () => {
    const transactions: Transaction[] = [
      createTransaction({ id: 1, description: 'Albert', category: undefined }),
      createTransaction({ id: 2, description: 'Albert', category: { id: 5, name: 'Groceries' } }),
    ]

    const pattern = createPattern({ description: 'albert', categoryId: 5, strict: true })
    const result = getPatternMatchResult(transactions, pattern)

    expect(result.total).toBe(2)
    expect(result.matched.length).toBe(1) // Only unassigned in strict mode
    expect(result.matched[0].id).toBe(1)
  })
})
