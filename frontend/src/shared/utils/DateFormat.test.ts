import { describe, it, expect } from 'vitest'
import {
  formatDate,
  formatDateFullMonthName,
  formatMonth,
  formatMonthShort,
  formatMonthFull,
  formatDateToLocalString,
} from './DateFormat'

describe('DateFormat', () => {
  describe('formatDate', () => {
    it('formats date with weekday, day, short month and year', () => {
      const result = formatDate('2024-01-15')
      // Should start with capital letter (Dutch: "Ma 15 jan 2024" or similar)
      expect(result).toMatch(/^[A-Z]/)
      expect(result).toContain('15')
      expect(result).toContain('2024')
    })

    it('capitalizes the first letter', () => {
      const result = formatDate('2024-06-20')
      expect(result.charAt(0)).toBe(result.charAt(0).toUpperCase())
    })
  })

  describe('formatDateFullMonthName', () => {
    it('formats date with full month name', () => {
      const result = formatDateFullMonthName('2024-01-15')
      // Dutch: "15 januari 2024"
      expect(result).toContain('15')
      expect(result).toContain('2024')
      // Should contain full Dutch month name
      expect(result.toLowerCase()).toContain('januari')
    })

    it('capitalizes the first letter', () => {
      const result = formatDateFullMonthName('2024-03-10')
      expect(result.charAt(0)).toBe(result.charAt(0).toUpperCase())
    })
  })

  describe('formatMonth', () => {
    it('formats month and year', () => {
      const result = formatMonth('2024-01-15')
      // Dutch: "januari 2024"
      expect(result.toLowerCase()).toContain('januari')
      expect(result).toContain('2024')
    })

    it('returns different months correctly', () => {
      const jan = formatMonth('2024-01-01')
      const dec = formatMonth('2024-12-01')
      expect(jan.toLowerCase()).toContain('januari')
      expect(dec.toLowerCase()).toContain('december')
    })
  })

  describe('formatMonthShort', () => {
    it('formats month (short) and year', () => {
      const result = formatMonthShort('2024-01-15')
      expect(result).toContain('2024')
      // Short month name like "jan" or "jan."
      expect(result.length).toBeLessThan(formatMonth('2024-01-15').length)
    })
  })

  describe('formatMonthFull', () => {
    it('formats only the full month name', () => {
      const result = formatMonthFull('2024-01-15')
      // Dutch full month name
      expect(result.toLowerCase()).toContain('januari')
      // Should not contain year
      expect(result).not.toContain('2024')
    })

    it('returns correct Dutch month names', () => {
      expect(formatMonthFull('2024-02-01').toLowerCase()).toContain('februari')
      expect(formatMonthFull('2024-03-01').toLowerCase()).toContain('maart')
      expect(formatMonthFull('2024-04-01').toLowerCase()).toContain('april')
      expect(formatMonthFull('2024-05-01').toLowerCase()).toContain('mei')
      expect(formatMonthFull('2024-06-01').toLowerCase()).toContain('juni')
    })
  })

  describe('formatDateToLocalString', () => {
    it('formats date as YYYY-MM-DD', () => {
      const date = new Date(2024, 0, 15) // January 15, 2024
      expect(formatDateToLocalString(date)).toBe('2024-01-15')
    })

    it('pads single digit month and day with zeros', () => {
      const date = new Date(2024, 0, 5) // January 5, 2024
      expect(formatDateToLocalString(date)).toBe('2024-01-05')
    })

    it('handles end of year dates', () => {
      const date = new Date(2024, 11, 31) // December 31, 2024
      expect(formatDateToLocalString(date)).toBe('2024-12-31')
    })

    it('handles double digit months and days', () => {
      const date = new Date(2024, 10, 25) // November 25, 2024
      expect(formatDateToLocalString(date)).toBe('2024-11-25')
    })
  })
})
