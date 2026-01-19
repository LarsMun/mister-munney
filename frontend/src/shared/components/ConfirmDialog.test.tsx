import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import ConfirmDialog from './ConfirmDialog'

describe('ConfirmDialog', () => {
  const defaultProps = {
    open: true,
    title: 'Confirm Action',
    onConfirm: vi.fn(),
    onCancel: vi.fn(),
  }

  it('renders when open is true', () => {
    render(<ConfirmDialog {...defaultProps} />)

    expect(screen.getByText('Confirm Action')).toBeInTheDocument()
  })

  it('does not render when open is false', () => {
    render(<ConfirmDialog {...defaultProps} open={false} />)

    expect(screen.queryByText('Confirm Action')).not.toBeInTheDocument()
  })

  it('renders description when provided', () => {
    render(
      <ConfirmDialog
        {...defaultProps}
        description="Are you sure you want to delete this item?"
      />
    )

    expect(
      screen.getByText('Are you sure you want to delete this item?')
    ).toBeInTheDocument()
  })

  it('calls onConfirm when confirm button is clicked', () => {
    const onConfirm = vi.fn()
    render(<ConfirmDialog {...defaultProps} onConfirm={onConfirm} />)

    fireEvent.click(screen.getByText('Verwijderen'))

    expect(onConfirm).toHaveBeenCalledTimes(1)
    expect(onConfirm).toHaveBeenCalledWith(undefined)
  })

  it('calls onCancel when cancel button is clicked', () => {
    const onCancel = vi.fn()
    render(<ConfirmDialog {...defaultProps} onCancel={onCancel} />)

    fireEvent.click(screen.getByText('Annuleren'))

    expect(onCancel).toHaveBeenCalledTimes(1)
  })

  it('calls onCancel when close button is clicked', () => {
    const onCancel = vi.fn()
    render(<ConfirmDialog {...defaultProps} onCancel={onCancel} />)

    // Find the X button (close button)
    const closeButtons = screen.getAllByRole('button')
    const closeButton = closeButtons.find((btn) =>
      btn.querySelector('svg') !== null &&
      !btn.textContent?.includes('Annuleren') &&
      !btn.textContent?.includes('Verwijderen')
    )

    if (closeButton) {
      fireEvent.click(closeButton)
      expect(onCancel).toHaveBeenCalledTimes(1)
    }
  })

  describe('with checkbox', () => {
    it('renders checkbox when checkbox prop is provided', () => {
      render(
        <ConfirmDialog
          {...defaultProps}
          checkbox={{ label: 'Also delete related items' }}
        />
      )

      expect(screen.getByRole('checkbox')).toBeInTheDocument()
      expect(screen.getByText('Also delete related items')).toBeInTheDocument()
    })

    it('checkbox is unchecked by default', () => {
      render(
        <ConfirmDialog
          {...defaultProps}
          checkbox={{ label: 'Check me' }}
        />
      )

      const checkbox = screen.getByRole('checkbox')
      expect(checkbox).not.toBeChecked()
    })

    it('checkbox respects defaultChecked', () => {
      render(
        <ConfirmDialog
          {...defaultProps}
          checkbox={{ label: 'Check me', defaultChecked: true }}
        />
      )

      const checkbox = screen.getByRole('checkbox')
      expect(checkbox).toBeChecked()
    })

    it('passes checkbox value to onConfirm', () => {
      const onConfirm = vi.fn()
      render(
        <ConfirmDialog
          {...defaultProps}
          onConfirm={onConfirm}
          checkbox={{ label: 'Check me' }}
        />
      )

      // Check the checkbox
      fireEvent.click(screen.getByRole('checkbox'))

      // Confirm
      fireEvent.click(screen.getByText('Verwijderen'))

      expect(onConfirm).toHaveBeenCalledWith(true)
    })

    it('passes false when checkbox is not checked', () => {
      const onConfirm = vi.fn()
      render(
        <ConfirmDialog
          {...defaultProps}
          onConfirm={onConfirm}
          checkbox={{ label: 'Check me' }}
        />
      )

      // Confirm without checking
      fireEvent.click(screen.getByText('Verwijderen'))

      expect(onConfirm).toHaveBeenCalledWith(false)
    })
  })
})
