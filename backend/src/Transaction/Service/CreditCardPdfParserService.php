<?php

namespace App\Transaction\Service;

use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use DateTime;

/**
 * Service to parse ING Credit Card PDF statements
 */
class CreditCardPdfParserService
{
    public function __construct(
        private readonly MoneyFactory $moneyFactory
    ) {
    }

    /**
     * Parse ING credit card statement text and extract transactions
     *
     * @param string $pdfText The extracted text content from the PDF
     * @return array Array of parsed transaction data
     */
    public function parseIngCreditCardStatement(string $pdfText): array
    {
        $transactions = [];
        $lines = explode("\n", $pdfText);

        $currentTransaction = null;
        $datePattern = '/^(\d{2})-(\d{2})-(\d{4})\s+(.+)/'; // DD-MM-YYYY format
        $inTransactionSection = false;

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Detect start of transaction section (after the header line)
            if (preg_match('/Geboekt op\s+Naam.*Type.*Bedrag/i', $line)) {
                $inTransactionSection = true;
                continue;
            }

            // Skip header information and footer
            if (!$inTransactionSection) {
                continue;
            }

            // Stop parsing at footer indicators
            if (preg_match('/Dit product valt onder|Pagina \d+ van \d+|ING Bank N\.V\.|A-PAYPS02/i', $line)) {
                $inTransactionSection = false;
                continue;
            }

            // Check if line starts with a date (transaction start)
            if (preg_match($datePattern, $line, $matches)) {
                // Save previous transaction if exists
                if ($currentTransaction !== null && isset($currentTransaction['description']) && isset($currentTransaction['amount'])) {
                    $transactions[] = $currentTransaction;
                }

                // Start new transaction
                $day = $matches[1];
                $month = $matches[2];
                $year = $matches[3];
                $restOfLine = trim($matches[4]);

                // Try to extract amount immediately (it's at the end of the line after +/-)
                $amount = $this->extractAmount($restOfLine);

                // Remove amount and type from description
                // Pattern: Remove everything after the last occurrence of Betaling/Incasso/Ontvangst/Kosten
                // Allows for optional spaces between sign and amount (e.g., "- 8,35" or "-8,35")
                $description = preg_replace('/\s+(Betaling|Incasso|Ontvangst|Kosten)\s+[-+]?\s*[\d\.,]+\s*$/', '', $restOfLine);
                if (empty(trim($description))) {
                    // Fallback: just use everything before the amount
                    $description = $restOfLine;
                }

                $currentTransaction = [
                    'geboekt_op' => "$year-$month-$day",
                    'description' => trim($description),
                    'amount' => $amount,
                    'transactie_datum' => null,
                    'card_number' => null,
                    'original_amount' => null,
                    'exchange_rate' => null,
                    'exchange_fee' => null,
                ];
            }
            // Check for transaction details on subsequent lines
            elseif ($currentTransaction !== null) {
                // Check for transaction date
                if (preg_match('/Transactiedatum:\s*(\d{2})-(\d{2})-(\d{4})/', $line, $matches)) {
                    $currentTransaction['transactie_datum'] = "{$matches[3]}-{$matches[2]}-{$matches[1]}";
                }
                // Check for card number
                elseif (preg_match('/Kaartnummer:\s*([\d\s\*]+)/', $line, $matches)) {
                    $currentTransaction['card_number'] = trim($matches[1]);
                }
                // Check for original amount (foreign currency)
                elseif (preg_match('/Bedrag:\s*([\d,\.]+)\s*([A-Z]{3})/', $line, $matches)) {
                    $currentTransaction['original_amount'] = [
                        'amount' => $matches[1],
                        'currency' => $matches[2]
                    ];
                }
                // Check for exchange rate
                elseif (preg_match('/Koers:\s*([\d,\.]+)/', $line, $matches)) {
                    $currentTransaction['exchange_rate'] = $matches[1];
                }
                // Check for exchange fee
                elseif (preg_match('/Koersopslag\s*\(EUR\):\s*([\d,\.]+)/', $line, $matches)) {
                    $currentTransaction['exchange_fee'] = $matches[1];
                }
                // Try to extract amount if still missing
                elseif ($currentTransaction['amount'] === null) {
                    $amount = $this->extractAmount($line);
                    if ($amount !== null) {
                        $currentTransaction['amount'] = $amount;
                    }
                }
            }
        }

        // Don't forget the last transaction
        if ($currentTransaction !== null && isset($currentTransaction['description']) && isset($currentTransaction['amount'])) {
            $transactions[] = $currentTransaction;
        }

        // Filter out AFLOSSING (payment to credit card itself) and convert to standard format
        return $this->convertToStandardFormat($transactions);
    }

    /**
     * Extract amount from a string
     * Handles formats like: "-21,19", "+416,66", "- 8,35", "+ 315,49", etc.
     * The +/- sign determines the transaction type:
     * - Negative (-) = DEBIT (expense, paid out)
     * - Positive (+) = CREDIT (refund, income)
     */
    private function extractAmount(string $text): ?float
    {
        // Match patterns like: -21,19 or +416,66 or - 8,35 or + 315,49 or -1.234,56
        // Sign is required (+ or -), with optional spaces between sign and number
        if (preg_match('/([-+])\s*([\d\.]+),([\d]{2})/', $text, $matches)) {
            $sign = $matches[1];
            $euros = str_replace('.', '', $matches[2]); // Remove thousand separators
            $cents = $matches[3];
            $amount = floatval("$euros.$cents");

            // Preserve the sign: negative for expenses, positive for refunds
            return $sign === '-' ? -abs($amount) : abs($amount);
        }

        return null;
    }

    /**
     * Convert parsed transactions to standard format for import
     * Transaction type is determined by the amount sign:
     * - Negative amount = DEBIT (expense)
     * - Positive amount = CREDIT (refund/income)
     */
    private function convertToStandardFormat(array $transactions): array
    {
        $standardized = [];

        foreach ($transactions as $tx) {
            // Skip AFLOSSING (payment/refund to credit card) - this is the incasso itself
            if (str_contains(strtoupper($tx['description'] ?? ''), 'AFLOSSING')) {
                continue;
            }

            // Use transaction date if available, otherwise use booking date
            $date = $tx['transactie_datum'] ?? $tx['geboekt_op'];

            $standardized[] = [
                'date' => $date,
                'description' => $tx['description'],
                'amount' => $tx['amount'], // Already in EUR with correct sign
                'transaction_type' => ($tx['amount'] < 0) ? TransactionType::DEBIT->value : TransactionType::CREDIT->value,
                'mutation_type' => 'Creditcard',
                'transaction_code' => 'CC',
                'notes' => $this->buildNotes($tx),
                'counterparty_account' => null,
                'tag' => 'creditcard',
            ];
        }

        return $standardized;
    }

    /**
     * Build notes field from transaction details
     */
    private function buildNotes(array $tx): string
    {
        $notes = [];

        if ($tx['card_number']) {
            $notes[] = "Kaartnummer: {$tx['card_number']}";
        }

        if ($tx['transactie_datum'] && $tx['transactie_datum'] !== $tx['geboekt_op']) {
            $notes[] = "Transactiedatum: {$tx['transactie_datum']}";
        }

        if ($tx['original_amount']) {
            $notes[] = "Oorspronkelijk bedrag: {$tx['original_amount']['amount']} {$tx['original_amount']['currency']}";
        }

        if ($tx['exchange_rate']) {
            $notes[] = "Wisselkoers: {$tx['exchange_rate']}";
        }

        if ($tx['exchange_fee']) {
            $notes[] = "Koersopslag: €{$tx['exchange_fee']}";
        }

        return implode(' | ', $notes);
    }

    /**
     * Validate that parsed transactions sum to the expected total
     * Compares absolute values since incasso and charges both have same sign
     */
    public function validateTotal(array $transactions, float $expectedTotal): bool
    {
        $sum = 0.0;
        foreach ($transactions as $tx) {
            $sum += $tx['amount'];
        }

        // Compare absolute values (incasso is negative, charges are negative)
        // Allow for small rounding differences (within 1 cent)
        return abs(abs($sum) - abs($expectedTotal)) < 0.01;
    }
}
