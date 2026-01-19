import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MonthPicker } from './MonthPicker'

describe('MonthPicker', () => {
  const defaultProps = {
    value: '2024-06',
    onChange: vi.fn(),
  }

  it('renders with formatted value', () => {
    render(<MonthPicker {...defaultProps} />)

    const input = screen.getByRole('textbox')
    // Value should be formatted as MM-YYYY for display
    expect(input).toHaveValue('06-2024')
  })

  it('renders with placeholder when value is empty', () => {
    render(<MonthPicker {...defaultProps} value="" />)

    const input = screen.getByRole('textbox')
    expect(input).toHaveAttribute('placeholder', 'MM-JJJJ')
  })

  it('renders with custom placeholder', () => {
    render(<MonthPicker {...defaultProps} value="" placeholder="Select month" />)

    const input = screen.getByRole('textbox')
    expect(input).toHaveAttribute('placeholder', 'Select month')
  })

  it('opens picker when input is clicked', () => {
    render(<MonthPicker {...defaultProps} />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    // Check that month buttons are visible
    expect(screen.getByText('Jan')).toBeInTheDocument()
    expect(screen.getByText('Dec')).toBeInTheDocument()
  })

  it('displays current year in picker', () => {
    render(<MonthPicker {...defaultProps} value="2024-06" />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    expect(screen.getByText('2024')).toBeInTheDocument()
  })

  it('calls onChange when month is selected', () => {
    const onChange = vi.fn()
    render(<MonthPicker {...defaultProps} onChange={onChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    // Click on March (Maa in Dutch)
    fireEvent.click(screen.getByText('Maa'))

    expect(onChange).toHaveBeenCalledWith('2024-03')
  })

  it('navigates to previous year', () => {
    render(<MonthPicker {...defaultProps} value="2024-06" />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    // Click previous year button
    fireEvent.click(screen.getByText('◀'))

    expect(screen.getByText('2023')).toBeInTheDocument()
  })

  it('navigates to next year', () => {
    render(<MonthPicker {...defaultProps} value="2024-06" />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    // Click next year button
    fireEvent.click(screen.getByText('▶'))

    expect(screen.getByText('2025')).toBeInTheDocument()
  })

  it('calls onChange on manual input', () => {
    const onChange = vi.fn()
    render(<MonthPicker {...defaultProps} onChange={onChange} />)

    const input = screen.getByRole('textbox')
    fireEvent.change(input, { target: { value: '12-2025' } })

    expect(onChange).toHaveBeenCalledWith('2025-12')
  })

  it('shows "Geen einddatum" button when allowEmpty is true', () => {
    render(<MonthPicker {...defaultProps} allowEmpty={true} />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    expect(screen.getByText('Geen einddatum')).toBeInTheDocument()
  })

  it('does not show "Geen einddatum" button when allowEmpty is false', () => {
    render(<MonthPicker {...defaultProps} allowEmpty={false} />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    expect(screen.queryByText('Geen einddatum')).not.toBeInTheDocument()
  })

  it('clears value when "Geen einddatum" is clicked', () => {
    const onChange = vi.fn()
    render(<MonthPicker {...defaultProps} onChange={onChange} allowEmpty={true} />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    fireEvent.click(screen.getByText('Geen einddatum'))

    expect(onChange).toHaveBeenCalledWith('')
  })

  it('highlights selected month', () => {
    render(<MonthPicker {...defaultProps} value="2024-06" />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    // June should have blue background class
    const juneButton = screen.getByText('Jun')
    expect(juneButton).toHaveClass('bg-blue-600', 'text-white')
  })

  it('closes picker after month selection', () => {
    render(<MonthPicker {...defaultProps} />)

    const input = screen.getByRole('textbox')
    fireEvent.click(input)

    // Month buttons should be visible
    expect(screen.getByText('Jan')).toBeInTheDocument()

    // Select a month
    fireEvent.click(screen.getByText('Jan'))

    // Picker should be closed (month buttons should not be visible)
    expect(screen.queryByText('Jan')).not.toBeInTheDocument()
  })

  it('applies autoFocus when set', () => {
    render(<MonthPicker {...defaultProps} autoFocus={true} />)

    const input = screen.getByRole('textbox')
    expect(document.activeElement).toBe(input)
  })
})
