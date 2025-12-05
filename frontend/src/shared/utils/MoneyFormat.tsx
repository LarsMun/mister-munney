/**
 * Format a number as currency according to Dutch/SI conventions.
 * Output format: € 1.234,56 (euro sign, space, dots for thousands, comma for decimals)
 *
 * @param amount - The amount in euros (not cents)
 * @param currency - Currency code (default: EUR)
 * @returns Formatted currency string
 */
export function formatMoney(amount: string | number, currency = "EUR"): string {
    const numAmount = typeof amount === "string" ? parseFloat(amount) : amount;

    // Use nl-NL locale for Dutch formatting: € 1.234,56
    return new Intl.NumberFormat("nl-NL", {
        style: "currency",
        currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(numAmount);
}

/**
 * Format a number with Dutch number formatting (no currency symbol).
 * Output format: 1.234,56 (dots for thousands, comma for decimals)
 *
 * @param amount - The amount to format
 * @param decimals - Number of decimal places (default: 2)
 * @returns Formatted number string
 */
export function formatNumber(amount: string | number, decimals = 2): string {
    const numAmount = typeof amount === "string" ? parseFloat(amount) : amount;

    return new Intl.NumberFormat("nl-NL", {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(numAmount);
}