<?php

namespace App\Transaction\Service;

/**
 * Service to parse PayPal CSV export files
 */
class PayPalCsvParserService
{
    /**
     * Transaction types to skip (not actual payments)
     */
    private const SKIP_TYPES = [
        'Algemene autorisatie',
        'Bankstorting naar PP-rekening',
        'Algemene valutaomrekening',
    ];

    /**
     * Valid status values (completed transactions)
     */
    private const VALID_STATUSES = [
        'Voltooid',      // Dutch
        'Completed',     // English
        'Terminé',       // French
        'Abgeschlossen', // German
    ];

    /**
     * Parse PayPal CSV export and extract relevant transactions
     *
     * CSV columns:
     * 0: Datum (dd-mm-yyyy)
     * 1: Tijd
     * 2: Tijdzone
     * 3: Naam (merchant)
     * 4: Type
     * 5: Status
     * 6: Valuta
     * 7: Bedrag
     * 8: Kosten
     * 9: Totaal
     * 10: Wisselkoers
     * 11: Ontvangstbewijsreferentie
     * 12: Saldo
     * 13: Transactiereferentie
     * 14: Objecttitel
     *
     * @param string $csvContent The CSV file content
     * @return array Array of parsed transaction data
     */
    public function parseCsv(string $csvContent): array
    {
        $transactions = [];
        $lines = explode("\n", $csvContent);

        // Skip header row
        $isFirstLine = true;

        foreach ($lines as $line) {
            $line = trim($line);

            if (empty($line)) {
                continue;
            }

            if ($isFirstLine) {
                $isFirstLine = false;
                continue;
            }

            $row = str_getcsv($line);

            if (count($row) < 14) {
                continue;
            }

            $transaction = $this->parseRow($row);

            if ($transaction !== null) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Parse a single CSV row
     */
    private function parseRow(array $row): ?array
    {
        $date = trim($row[0] ?? '');
        $merchant = trim($row[3] ?? '');
        $type = trim($row[4] ?? '');
        $status = trim($row[5] ?? '');
        $currency = trim($row[6] ?? '');
        $amount = trim($row[7] ?? '');
        $reference = trim($row[13] ?? '');

        // Skip if not completed (support multiple languages)
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return null;
        }

        // Skip certain transaction types
        if (in_array($type, self::SKIP_TYPES, true)) {
            return null;
        }

        // Skip if no merchant name
        if (empty($merchant)) {
            return null;
        }

        // Parse amount
        $parsedAmount = $this->parseAmount($amount);
        if ($parsedAmount === null) {
            return null;
        }

        // Only import expenses (negative amounts)
        if ($parsedAmount >= 0) {
            return null;
        }

        // Parse date (format: dd-mm-yyyy)
        $parsedDate = $this->parseDate($date);
        if ($parsedDate === null) {
            return null;
        }

        return [
            'date' => $parsedDate,
            'merchant' => $merchant,
            'amount' => $parsedAmount,
            'currency' => $currency,
            'reference' => $reference,
            'type' => $type,
        ];
    }

    /**
     * Parse amount from CSV format (e.g., "-22,06")
     */
    private function parseAmount(string $amount): ?float
    {
        if (empty($amount)) {
            return null;
        }

        // Replace comma with dot for decimal separator
        $amount = str_replace(',', '.', $amount);

        // Remove any spaces
        $amount = str_replace(' ', '', $amount);

        if (!is_numeric($amount)) {
            return null;
        }

        return (float) $amount;
    }

    /**
     * Parse date from format dd-mm-yyyy to Y-m-d
     */
    private function parseDate(string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        // Parse dd-mm-yyyy format
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return null;
        }

        $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $year = $parts[2];

        // Validate
        if (!checkdate((int) $month, (int) $day, (int) $year)) {
            return null;
        }

        return "$year-$month-$day";
    }
}
