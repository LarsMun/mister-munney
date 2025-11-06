<?php

namespace App\Transaction\Service;

use App\Money\MoneyFactory;

/**
 * Service to parse copy-pasted PayPal web interface transaction list
 */
class PayPalWebPasteParserService
{
    private const MONTH_MAP = [
        'jan' => '01', 'feb' => '02', 'mrt' => '03', 'apr' => '04',
        'mei' => '05', 'jun' => '06', 'jul' => '07', 'aug' => '08',
        'sep' => '09', 'okt' => '10', 'nov' => '11', 'dec' => '12',
    ];

    public function __construct(
        private readonly MoneyFactory $moneyFactory
    ) {
    }

    /**
     * Parse PayPal web interface copy-paste and extract transactions
     *
     * Expected format:
     * Merchant Name
     * −€ 22,52
     * 24 okt. . Automatische betaling
     *
     * With month headers like: "okt. 2025"
     *
     * @param string $pastedText The pasted text from PayPal website
     * @return array Array of parsed transaction data
     */
    public function parsePayPalWebPaste(string $pastedText): array
    {
        $transactions = [];
        $lines = explode("\n", $pastedText);

        $currentYear = null;
        $currentMonth = null;

        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check for month header (e.g., "okt. 2025", "sep. 2025")
            if (preg_match('/^([a-z]{3})\.\s+(\d{4})$/i', $line, $matches)) {
                $monthAbbrev = strtolower($matches[1]);
                $currentYear = $matches[2];
                $currentMonth = self::MONTH_MAP[$monthAbbrev] ?? null;
                continue;
            }

            // Check if this line is an amount line (−€ or −$)
            if ($this->isAmountLine($line) && $i > 0 && $i + 1 < count($lines)) {
                $merchantLine = trim($lines[$i - 1]);
                $amountLine = $line;
                $dateLine = trim($lines[$i + 1]);

                $transaction = $this->parseTransaction($merchantLine, $amountLine, $dateLine, $currentYear, $currentMonth);

                if ($transaction !== null) {
                    $transactions[] = $transaction;
                }
            }
        }

        return $transactions;
    }

    /**
     * Parse a single transaction from 3 lines
     */
    private function parseTransaction(
        string $merchantLine,
        string $amountLine,
        string $dateLine,
        ?string $year,
        ?string $month
    ): ?array {
        // Parse amount
        $amount = $this->parseAmount($amountLine);
        if ($amount === null) {
            return null;
        }

        // Parse date (format: "24 okt. . Automatische betaling")
        $date = $this->parseDate($dateLine, $year, $month);
        if ($date === null) {
            return null;
        }

        // Merchant name is just the first line (cleaned)
        $merchant = $this->cleanMerchantName($merchantLine);
        if (empty($merchant)) {
            return null;
        }

        return [
            'date' => $date,
            'merchant' => $merchant,
            'amount' => $amount, // In euros (negative for expenses)
        ];
    }

    /**
     * Check if a line looks like an amount line
     * Matches both Unicode minus (−) and regular hyphen-minus (-)
     */
    private function isAmountLine(string $line): bool
    {
        return preg_match('/^[−\-][€$]\s*[\d,\.]+(\s+[A-Z]{3})?$/u', $line) === 1;
    }

    /**
     * Parse amount from PayPal web format
     * Handles: "−€ 22,52", "−$ 3,78 USD", "-€ 22,52"
     * Supports both Unicode minus (−) and regular hyphen-minus (-)
     */
    private function parseAmount(string $text): ?float
    {
        // Match patterns like: −€ 22,52 or −$ 3,78 USD or -€ 22,52
        if (preg_match('/[−\-][€$]\s*([\d,\.]+)/u', $text, $matches)) {
            $amountStr = $matches[1];

            // Replace comma with dot for decimal separator
            $amountStr = str_replace(',', '.', $amountStr);
            $amountStr = str_replace('.', '', $amountStr); // Remove thousands separator if any

            // Re-add decimal separator (assume last 2 digits are cents)
            if (strlen($amountStr) > 2) {
                $amountStr = substr($amountStr, 0, -2) . '.' . substr($amountStr, -2);
            } else {
                $amountStr = '0.' . str_pad($amountStr, 2, '0', STR_PAD_LEFT);
            }

            $amount = floatval($amountStr);

            // Convert USD to EUR if needed (approximate rate, will be corrected by matching)
            if (str_contains($text, 'USD')) {
                $amount = $amount * 0.92; // Rough EUR/USD conversion
            }

            return -abs($amount); // Always negative for expenses
        }

        return null;
    }

    /**
     * Parse date from format "24 okt. . Automatische betaling"
     */
    private function parseDate(string $dateLine, ?string $year, ?string $month): ?string
    {
        // Extract date part (before the first dot)
        if (preg_match('/^(\d{1,2})\s+([a-z]{3})\.\s*\./i', $dateLine, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $monthAbbrev = strtolower($matches[2]);
            $parsedMonth = self::MONTH_MAP[$monthAbbrev] ?? $month;

            if ($year && $parsedMonth) {
                return "$year-$parsedMonth-$day";
            }
        }

        return null;
    }

    /**
     * Clean merchant name (remove extra whitespace, HTML entities, etc.)
     */
    private function cleanMerchantName(string $name): string
    {
        // Trim and normalize whitespace
        $name = trim($name);
        $name = preg_replace('/\s+/', ' ', $name);

        return $name;
    }
}
