export function formatMoney(amountInCents: string | number, currency = "EUR"): string {
    const amount = typeof amountInCents === "string" ? parseInt(amountInCents, 10) : amountInCents;

    return new Intl.NumberFormat("nl-NL", {
        style: "currency",
        currency,
        minimumFractionDigits: 2,
    }).format(amount);
}