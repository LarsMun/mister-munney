<?php

namespace App\Tests\Fixtures;

class CsvTestFixtures
{
    /**
     * Generate a valid ING CSV for testing
     */
    public static function getValidRabobankCsv(): array
    {
        return [
            // Header row (ING format)
            [
                'Datum',
                'Naam / Omschrijving',
                'Rekening',
                'Tegenrekening',
                'Code',
                'Af Bij',
                'Bedrag (EUR)',
                'Mutatiesoort',
                'Mededelingen',
                'Saldo na mutatie',
                'Tag'
            ],
            // Transaction 1 - Debit (Supermarket)
            [
                '20240115',
                'Albert Heijn 1234 Amsterdam',
                'NL91INGB0123456789',
                'NL00ABNA0000000000',
                'BA',
                'Af',
                '25,50',
                'Betaalpas',
                'Betaling Albert Heijn',
                '1474,50',
                ''
            ],
            // Transaction 2 - Credit (Salary)
            [
                '20240101',
                'Salaris maand januari',
                'NL91INGB0123456789',
                'NL00INGB0000000000',
                'OV',
                'Bij',
                '3000,00',
                'Overschrijving',
                'Company XYZ B.V.',
                '4500,00',
                ''
            ],
            // Transaction 3 - Debit (Another supermarket)
            [
                '20240120',
                'Jumbo Amsterdam Centrum',
                'NL91INGB0123456789',
                'NL00ABNA0111111111',
                'BA',
                'Af',
                '18,75',
                'Betaalpas',
                'Contactloos betalen',
                '1455,75',
                ''
            ]
        ];
    }

    /**
     * Generate CSV with invalid format (wrong headers)
     */
    public static function getInvalidCsv(): array
    {
        return [
            ['Invalid', 'Header', 'Row', 'Format'],
            ['Missing', 'Required', 'Columns', 'Here'],
        ];
    }

    /**
     * Generate CSV with duplicate transaction
     */
    public static function getCsvWithDuplicates(): array
    {
        $valid = self::getValidRabobankCsv();

        // Add duplicate of first transaction (same data = same hash)
        $valid[] = $valid[1]; // Duplicate transaction 1

        return $valid;
    }

    /**
     * Generate empty CSV (header only, no data rows)
     */
    public static function getEmptyCsv(): array
    {
        return [
            self::getValidRabobankCsv()[0] // Only header
        ];
    }

    /**
     * Save CSV to temporary file
     */
    public static function saveCsvToFile(array $rows): string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_csv_') . '.csv'; // Add .csv extension!
        $handle = fopen($tmpFile, 'w');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';'); // ING uses semicolon as delimiter
        }

        fclose($handle);
        return $tmpFile;
    }

    /**
     * Clean up temp file
     */
    public static function cleanup(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
}
