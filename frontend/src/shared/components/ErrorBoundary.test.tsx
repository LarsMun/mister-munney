import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import ErrorBoundary from './ErrorBoundary'

// Component that throws an error
const ThrowError = ({ shouldThrow }: { shouldThrow: boolean }) => {
  if (shouldThrow) {
    throw new Error('Test error')
  }
  return <div>Child content</div>
}

describe('ErrorBoundary', () => {
  // Suppress console.error during tests since we expect errors
  const originalError = console.error
  beforeEach(() => {
    console.error = vi.fn()
  })
  afterEach(() => {
    console.error = originalError
  })

  it('renders children when there is no error', () => {
    render(
      <ErrorBoundary>
        <div>Child content</div>
      </ErrorBoundary>
    )

    expect(screen.getByText('Child content')).toBeInTheDocument()
  })

  it('renders error UI when child throws', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    expect(screen.getByText('Er is iets misgegaan')).toBeInTheDocument()
    expect(
      screen.getByText(/Er is een onverwachte fout opgetreden/)
    ).toBeInTheDocument()
  })

  it('renders custom fallback when provided', () => {
    render(
      <ErrorBoundary fallback={<div>Custom error message</div>}>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    expect(screen.getByText('Custom error message')).toBeInTheDocument()
  })

  it('shows retry button', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    expect(screen.getByText('Opnieuw proberen')).toBeInTheDocument()
  })

  it('shows reload button', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    expect(screen.getByText('Pagina herladen')).toBeInTheDocument()
  })

  it('shows home button', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    expect(screen.getByText('Naar homepage')).toBeInTheDocument()
  })

  it('logs error to console', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    expect(console.error).toHaveBeenCalled()
  })

  it('reloads page when reload button is clicked', () => {
    const originalLocation = window.location
    const mockReload = vi.fn()

    // Mock window.location.reload
    Object.defineProperty(window, 'location', {
      value: { ...originalLocation, reload: mockReload },
      writable: true,
    })

    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    fireEvent.click(screen.getByText('Pagina herladen'))

    expect(mockReload).toHaveBeenCalled()

    // Restore
    Object.defineProperty(window, 'location', {
      value: originalLocation,
      writable: true,
    })
  })

  it('navigates to home when home button is clicked', () => {
    const originalLocation = window.location

    // Mock window.location.href setter
    let href = ''
    Object.defineProperty(window, 'location', {
      value: {
        ...originalLocation,
        get href() {
          return href
        },
        set href(value: string) {
          href = value
        },
      },
      writable: true,
    })

    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    )

    fireEvent.click(screen.getByText('Naar homepage'))

    expect(href).toBe('/')

    // Restore
    Object.defineProperty(window, 'location', {
      value: originalLocation,
      writable: true,
    })
  })
})
