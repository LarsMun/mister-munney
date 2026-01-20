<?php

namespace App\RecurringTransaction\Service;

use App\Entity\Transaction;

class MerchantNormalizer
{
    /**
     * Patterns to strip from descriptions
     */
    private const DATE_PATTERNS = [
        '/\b\d{1,2}[-\/]\d{1,2}[-\/]\d{2,4}\b/',  // DD-MM-YYYY, DD/MM/YY
        '/\b\d{4}[-\/]\d{1,2}[-\/]\d{1,2}\b/',     // YYYY-MM-DD
        '/\b\d{1,2}\s+(jan|feb|mrt|apr|mei|jun|jul|aug|sep|okt|nov|dec)\b/i', // 12 jan
        '/\b(januari|februari|maart|april|mei|juni|juli|augustus|september|oktober|november|december)\s+\d{4}\b/i',
    ];

    private const REFERENCE_PATTERNS = [
        '/\b[A-Z]{2}\d{2}[A-Z0-9]{4}\d{7}[A-Z0-9]{0,16}\b/',  // IBAN pattern (keep separately)
        '/\bRef[:\s.]*[A-Z0-9-]+\b/i',                          // Ref: ABC123
        '/\bNr[:\s.]*[A-Z0-9-]+\b/i',                           // Nr: 12345
        '/\b(order|factuur|inv)[:\s#]*[A-Z0-9-]+\b/i',         // Order #123, Factuur 456
        '/\b[A-Z]{2,4}\d{6,12}\b/',                             // Reference numbers like AB123456
        '/\b\d{10,16}\b/',                                       // Long numeric sequences
        '/\*{4}\s*\d{4}\b/',                                     // Card numbers ****1234
    ];

    private const CLEANUP_PATTERNS = [
        '/\s+/',                  // Multiple spaces to single space
        '/[,.:;]+\s*$/',         // Trailing punctuation
        '/^\s*[,.:;]+/',         // Leading punctuation
    ];

    /**
     * Normalize a transaction into a merchant identifier.
     * Prefers IBAN if available, otherwise normalizes the description.
     * Includes category to differentiate transfers to the same account for different purposes.
     */
    public function normalize(Transaction $transaction): string
    {
        $pattern = '';

        // If we have a counterparty IBAN, use that as the most reliable identifier
        $iban = $transaction->getCounterpartyAccount();
        if ($iban !== null && $this->isValidIban($iban)) {
            $pattern = 'IBAN:' . strtoupper(trim($iban));
        } else {
            // Fall back to normalizing the description
            $pattern = $this->normalizeDescription($transaction->getDescription() ?? '');
        }

        // Include category to differentiate transfers to the same account for different purposes
        // (e.g., monthly savings to different savings goals)
        $category = $transaction->getCategory();
        if ($category !== null) {
            $pattern .= '|CAT:' . $category->getId();
        }

        return $pattern;
    }

    /**
     * Create a display-friendly name from a transaction
     */
    public function extractDisplayName(Transaction $transaction): string
    {
        $description = $transaction->getDescription() ?? '';

        // Start with a cleaned up description
        $name = $this->normalizeDescription($description);

        // Try to extract just the merchant name (first meaningful part)
        $parts = preg_split('/\s{2,}|[,]/', $name);
        if ($parts !== false && count($parts) > 0) {
            $name = trim($parts[0]);
        }

        // Capitalize properly
        $name = ucwords(strtolower($name));

        // Limit length
        if (mb_strlen($name) > 50) {
            $name = mb_substr($name, 0, 47) . '...';
        }

        return $name ?: 'Onbekend';
    }

    /**
     * Normalize description by stripping dates, references, and noise
     */
    private function normalizeDescription(string $description): string
    {
        $normalized = $description;

        // Strip date patterns
        foreach (self::DATE_PATTERNS as $pattern) {
            $normalized = preg_replace($pattern, ' ', $normalized);
        }

        // Strip reference patterns
        foreach (self::REFERENCE_PATTERNS as $pattern) {
            $normalized = preg_replace($pattern, ' ', $normalized);
        }

        // Basic cleanup
        $normalized = strtolower($normalized);

        // Cleanup whitespace and punctuation
        foreach (self::CLEANUP_PATTERNS as $pattern) {
            $normalized = preg_replace($pattern, ' ', $normalized);
        }

        return trim($normalized);
    }

    /**
     * Basic IBAN validation
     */
    private function isValidIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        // Check length (varies by country, but most are 15-34 chars)
        $length = strlen($iban);
        if ($length < 15 || $length > 34) {
            return false;
        }

        // Check if it starts with 2 letters followed by 2 digits
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            return false;
        }

        return true;
    }

    /**
     * Check if two normalized patterns likely represent the same merchant
     */
    public function isSameMerchant(string $pattern1, string $pattern2): bool
    {
        // Exact match
        if ($pattern1 === $pattern2) {
            return true;
        }

        // If both are IBANs, they must match exactly (already handled above)
        if (str_starts_with($pattern1, 'IBAN:') || str_starts_with($pattern2, 'IBAN:')) {
            return false;
        }

        // Check similarity using Levenshtein distance
        $maxLen = max(strlen($pattern1), strlen($pattern2));
        if ($maxLen === 0) {
            return true;
        }

        $distance = levenshtein($pattern1, $pattern2);
        $similarity = 1 - ($distance / $maxLen);

        return $similarity >= 0.85;
    }
}
