import { describe, it, expect, vi } from 'vitest'
import { getRandomPastelHex, darkenColor } from './categoryUtils'

describe('categoryUtils', () => {
  describe('getRandomPastelHex', () => {
    it('returns a valid hex color', () => {
      const color = getRandomPastelHex()
      expect(color).toMatch(/^#[0-9A-Fa-f]{6}$/)
    })

    it('returns a pastel color from the predefined list', () => {
      const pastelColors = [
        '#FFB3BA', '#FFDFBA', '#FFFFBA', '#BAFFC9', '#BAE1FF',
        '#D7BAFF', '#FFBADC', '#BFFCC6', '#B9FBC0', '#A0CED9',
        '#FDCBFA', '#C4FAF8', '#F6D186', '#D5AAFF', '#FFDAC1',
        '#C1C8E4', '#A8E6CF', '#FFD3B6', '#E2F0CB', '#C7CEEA',
        '#EEEEEE', '#FFFFFF',
      ]

      // Call multiple times to increase confidence
      for (let i = 0; i < 100; i++) {
        const color = getRandomPastelHex()
        expect(pastelColors).toContain(color)
      }
    })

    it('returns different colors over multiple calls', () => {
      const colors = new Set<string>()

      // Generate 50 colors and expect at least 2 different ones
      for (let i = 0; i < 50; i++) {
        colors.add(getRandomPastelHex())
      }

      expect(colors.size).toBeGreaterThan(1)
    })

    it('returns consistent results with mocked Math.random', () => {
      vi.spyOn(Math, 'random').mockReturnValue(0)
      expect(getRandomPastelHex()).toBe('#FFB3BA')

      vi.spyOn(Math, 'random').mockReturnValue(0.9999)
      expect(getRandomPastelHex()).toBe('#FFFFFF')

      vi.restoreAllMocks()
    })
  })

  describe('darkenColor', () => {
    it('darkens a color by the specified percent', () => {
      // White (#FFFFFF) darkened by 50 should give #CDCDCD (255 - 50 = 205 = 0xCD)
      const result = darkenColor('#FFFFFF', 50)
      expect(result).toBe('#cdcdcd')
    })

    it('handles already dark colors', () => {
      // Black (#000000) darkened should stay at #000000 (clamped to 0)
      const result = darkenColor('#000000', 50)
      expect(result).toBe('#000000')
    })

    it('handles colors without hash', () => {
      const result = darkenColor('#FF0000', 50)
      expect(result).toMatch(/^#[0-9a-f]{6}$/)
    })

    it('clamps values to 0 instead of going negative', () => {
      // Very dark red (#100000) with large percent should clamp to 0
      const result = darkenColor('#100000', 255)
      expect(result).toBe('#000000')
    })

    it('preserves color balance', () => {
      // Red (#FF0000) darkened by 50 should give #CD0000 (255 - 50 = 205 = 0xCD)
      const result = darkenColor('#FF0000', 50)
      expect(result).toBe('#cd0000')
    })

    it('handles pastel colors', () => {
      // A pastel pink (#FFB3BA) darkened by 30
      const result = darkenColor('#FFB3BA', 30)
      expect(result).toMatch(/^#[0-9a-f]{6}$/)

      // Result should be darker (smaller RGB values)
      const originalR = parseInt('FF', 16)
      const resultR = parseInt(result.slice(1, 3), 16)
      expect(resultR).toBeLessThan(originalR)
    })
  })
})
