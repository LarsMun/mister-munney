import { describe, it, expect } from 'vitest'
import { formatMoney, formatNumber } from './MoneyFormat'

describe('MoneyFormat', () => {
  describe('formatMoney', () => {
    it('formats positive amounts with euro sign', () => {
      const result = formatMoney(1234.56)
      // Dutch format: € 1.234,56
      expect(result).toContain('€')
      expect(result).toContain('1.234')
      expect(result).toContain(',56')
    })

    it('formats zero correctly', () => {
      const result = formatMoney(0)
      expect(result).toContain('€')
      expect(result).toContain('0,00')
    })

    it('formats negative amounts', () => {
      const result = formatMoney(-500.50)
      expect(result).toContain('€')
      expect(result).toContain('500')
      expect(result).toContain(',50')
    })

    it('handles string input', () => {
      const result = formatMoney('1234.56')
      expect(result).toContain('€')
      expect(result).toContain('1.234')
      expect(result).toContain(',56')
    })

    it('rounds to two decimal places', () => {
      const result = formatMoney(100.999)
      // Should round to 101,00
      expect(result).toContain('101,00')
    })

    it('adds two decimal places to whole numbers', () => {
      const result = formatMoney(100)
      expect(result).toContain('100,00')
    })

    it('uses thousands separator for large amounts', () => {
      const result = formatMoney(1000000)
      // Dutch format uses dots as thousands separator
      expect(result).toContain('1.000.000')
    })

    it('handles small amounts correctly', () => {
      const result = formatMoney(0.01)
      expect(result).toContain('0,01')
    })
  })

  describe('formatNumber', () => {
    it('formats numbers without currency symbol', () => {
      const result = formatNumber(1234.56)
      expect(result).not.toContain('€')
      expect(result).toContain('1.234')
      expect(result).toContain(',56')
    })

    it('respects custom decimal places', () => {
      const result = formatNumber(100.5678, 4)
      expect(result).toContain(',5678')
    })

    it('formats with zero decimals', () => {
      const result = formatNumber(1234.56, 0)
      expect(result).toBe('1.235') // rounded
    })

    it('handles string input', () => {
      const result = formatNumber('5000')
      expect(result).toBe('5.000,00')
    })

    it('handles negative numbers', () => {
      const result = formatNumber(-1234.56)
      expect(result).toContain('1.234')
      expect(result).toContain(',56')
    })

    it('defaults to 2 decimal places', () => {
      const result = formatNumber(100)
      expect(result).toBe('100,00')
    })
  })
})
