import { describe, it, expect } from 'vitest'
import {
  isApiError,
  getErrorMessage,
  toApiError,
  requiresCaptcha,
  getFailedAttempts,
  type ApiError,
} from './errorUtils'

describe('errorUtils', () => {
  describe('isApiError', () => {
    it('returns true for valid ApiError objects', () => {
      const error: ApiError = { message: 'Test error' }
      expect(isApiError(error)).toBe(true)
    })

    it('returns true for ApiError with optional fields', () => {
      const error: ApiError = {
        message: 'Test error',
        status: 400,
        code: 'VALIDATION_ERROR',
        requiresCaptcha: true,
        failedAttempts: 3,
      }
      expect(isApiError(error)).toBe(true)
    })

    it('returns false for null', () => {
      expect(isApiError(null)).toBe(false)
    })

    it('returns false for undefined', () => {
      expect(isApiError(undefined)).toBe(false)
    })

    it('returns false for strings', () => {
      expect(isApiError('error')).toBe(false)
    })

    it('returns false for objects without message', () => {
      expect(isApiError({ code: 'ERROR' })).toBe(false)
    })

    it('returns false for objects with non-string message', () => {
      expect(isApiError({ message: 123 })).toBe(false)
    })
  })

  describe('getErrorMessage', () => {
    it('returns message from ApiError', () => {
      const error: ApiError = { message: 'Api error message' }
      expect(getErrorMessage(error)).toBe('Api error message')
    })

    it('returns message from Error instance', () => {
      const error = new Error('Standard error message')
      expect(getErrorMessage(error)).toBe('Standard error message')
    })

    it('returns string error directly', () => {
      expect(getErrorMessage('String error')).toBe('String error')
    })

    it('returns default message for unknown error types', () => {
      expect(getErrorMessage(123)).toBe('Er is een onbekende fout opgetreden')
      expect(getErrorMessage(null)).toBe('Er is een onbekende fout opgetreden')
      expect(getErrorMessage(undefined)).toBe('Er is een onbekende fout opgetreden')
      expect(getErrorMessage({})).toBe('Er is een onbekende fout opgetreden')
    })
  })

  describe('toApiError', () => {
    it('returns ApiError unchanged', () => {
      const error: ApiError = { message: 'Test', status: 400 }
      expect(toApiError(error)).toEqual(error)
    })

    it('converts Error to ApiError', () => {
      const error = new Error('Standard error')
      const result = toApiError(error)
      expect(result.message).toBe('Standard error')
    })

    it('converts string to ApiError', () => {
      expect(toApiError('String error')).toEqual({ message: 'String error' })
    })

    it('returns default ApiError for unknown types', () => {
      expect(toApiError(123)).toEqual({ message: 'Er is een onbekende fout opgetreden' })
      expect(toApiError(null)).toEqual({ message: 'Er is een onbekende fout opgetreden' })
    })
  })

  describe('requiresCaptcha', () => {
    it('returns true when requiresCaptcha is true', () => {
      const error: ApiError = { message: 'Error', requiresCaptcha: true }
      expect(requiresCaptcha(error)).toBe(true)
    })

    it('returns false when requiresCaptcha is false', () => {
      const error: ApiError = { message: 'Error', requiresCaptcha: false }
      expect(requiresCaptcha(error)).toBe(false)
    })

    it('returns false when requiresCaptcha is undefined', () => {
      const error: ApiError = { message: 'Error' }
      expect(requiresCaptcha(error)).toBe(false)
    })

    it('returns false for non-ApiError', () => {
      expect(requiresCaptcha('error')).toBe(false)
      expect(requiresCaptcha(null)).toBe(false)
    })
  })

  describe('getFailedAttempts', () => {
    it('returns failedAttempts from ApiError', () => {
      const error: ApiError = { message: 'Error', failedAttempts: 5 }
      expect(getFailedAttempts(error)).toBe(5)
    })

    it('returns 0 when failedAttempts is undefined', () => {
      const error: ApiError = { message: 'Error' }
      expect(getFailedAttempts(error)).toBe(0)
    })

    it('returns 0 for non-ApiError', () => {
      expect(getFailedAttempts('error')).toBe(0)
      expect(getFailedAttempts(null)).toBe(0)
    })
  })
})
