<?php

namespace App\Transaction\Service;

use App\Account\Service\AccountService;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Money\MoneyFactory;
use App\Pattern\Repository\PatternRepository;
use App\Pattern\Service\PatternAssignService;
use App\Transaction\Repository\TransactionRepository;
use DateTime;
use Exception;
use League\Csv\Exception as CsvException;
use League\Csv\InvalidArgument;
use League\Csv\Reader;
use League\Csv\UnavailableStream;
use Money\Money;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Service voor het importeren van transacties.
 *
 * Verwerkt CSV-bestanden, detecteert duplicaten op basis van hashes,
 * koppelt automatisch spaarrekeningen aan transacties via patroonherkenning.
 */
class TransactionImportService
{
    private TransactionRepository $transactionRepository;
    private LoggerInterface $logger;
    private AccountService $accountService;
    private MoneyFactory $moneyFactory;
    private PatternAssignService $patternAssignService;
    private PatternRepository $patternRepository;

    private array $existingTransactions = [];
    private $currentUser = null;
    private const string REQUIRED_EXTENSION = 'csv';
    private const array CSV_LAYOUT = [
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
    ];
    private const array REQUIRED_FIELDS = [
        'Datum',
        'Naam / Omschrijving',
        'Rekening',
        'Af Bij',
        'Bedrag (EUR)',
        'Saldo na mutatie'
    ];
    private const array HASH_FIELDS = [
        'Datum',
        'Naam / Omschrijving',
        'Rekening',
        'Tegenrekening',
        'Af Bij',
        'Bedrag (EUR)',
        'Saldo na mutatie'
    ];

    private const string FIELD_DATE = 'Datum';
    private const string FIELD_TYPE = 'Af Bij';
    private const string FIELD_AMOUNT = 'Bedrag (EUR)';
    private const string FIELD_BALANCE = 'Saldo na mutatie';
    private const string FIELD_DESCRIPTION = 'Naam / Omschrijving';
    private const string FIELD_ACCOUNT = 'Rekening';
    private const string FIELD_COUNTERPARTY = 'Tegenrekening';
    private const string FIELD_CODE = 'Code';
    private const string FIELD_MUTATION_TYPE = 'Mutatiesoort';
    private const string FIELD_NOTES = 'Mededelingen';
    private const string FIELD_TAG = 'Tag';

    public function __construct(
        TransactionRepository $transactionRepository,
        LoggerInterface $logger,
        AccountService $accountService,
        MoneyFactory $moneyFactory,
        PatternAssignService $patternAssignService,
        PatternRepository $patternRepository,
    )
    {
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->accountService = $accountService;
        $this->moneyFactory = $moneyFactory;
        $this->patternAssignService = $patternAssignService;
        $this->patternRepository = $patternRepository;
    }

    /**
     * Importeert een CSV-bestand met transacties.
     *
     * - Valideert bestandstype
     * - Controleert op duplicaten via unieke hash
     * - Voegt alleen nieuwe transacties toe
     * - Voert automatische pattern-matching uit op spaarrekeningen
     *
     * @param UploadedFile $file Het geüploade CSV-bestand
     * @return array Resultaat met status, importdetails en eventuele fouten
     *
     *
     * @throws RuntimeException Bij onverwachte fouten tijdens het lezen of verwerken van de CSV
     *
     */
    public function import(UploadedFile $file): array
    {
        $this->logger->info("CSV import gestart: " . $file->getClientOriginalName());

        if (!$this->isValidCsvFile($file)) {
            throw new BadRequestHttpException('Ongeldig bestand. Alleen CSV van ING toegestaan.');
        }

        try {
            $csv = $this->readCsvFile($file);
            $this->validateCsvHeader($csv->getHeader());
            
            // Check if CSV has any data rows (not just header)
            $records = iterator_to_array($csv->getRecords());
            if (empty($records)) {
                throw new BadRequestHttpException('CSV bestand is leeg (alleen header, geen transacties).');
            }
            
            $dates = $this->extractUniqueDatesFromCsv($records);
            $this->loadExistingTransactions($dates);
            [$imported, $skipped, $errors] = $this->processRecords($records);

            return $this->generateResponse($imported, $skipped, $errors);
        } catch (BadRequestHttpException $e) {
            throw $e; // Re-throw validation errors
        } catch (CsvException $e) {
            $this->logger->critical("Onverwachte fout tijdens verwerking CSV: " . $e->getMessage());
            throw new BadRequestHttpException('Onverwachte fout tijdens CSV-import: ' . $e->getMessage());
        }
    }

    /**
     * Importeert een CSV-bestand en linkt automatisch aangemaakte accounts aan de gebruiker
     *
     * @param UploadedFile $file Het CSV-bestand
     * @param mixed $user De user entity
     * @return array Resultaat met status en importdetails
     */
    public function importForUser(UploadedFile $file, $user): array
    {
        // Set current user so createTransactionEntity can link accounts
        $this->currentUser = $user;

        try {
            return $this->import($file);
        } finally {
            // Clear current user after import
            $this->currentUser = null;
        }
    }

    /**
     * Controleert of het geüploade bestand een geldig CSV-bestand is.
     *
     * Deze methode valideert het bestandstype op basis van de extensie.
     * Alleen bestanden met de extensie `.csv` worden geaccepteerd.
     *
     * @param UploadedFile $file Het geüploade bestand
     * @return bool True als het bestand een CSV-bestand is, anders false
     */
    private function isValidCsvFile(UploadedFile $file): bool
    {
        // In test environment, just check the extension
        // In production, the browser will set proper mime type
        $extension = strtolower($file->getClientOriginalExtension());
        return $extension === self::REQUIRED_EXTENSION;
    }

    /**
     * Leest een CSV-bestand en bereidt het voor op verwerking.
     *
     * - Stelt het scheidingsteken in op ";"
     * - Verwacht een header op de eerste rij (offset 0)
     * - Retourneert een League\Csv\Reader instantie voor iteratie
     *
     * @param UploadedFile $file Het geüploade CSV-bestand
     * @return Reader De geconfigureerde CSV-lezer
     *
     * @throws RuntimeException Als het bestand niet geopend of gelezen kan worden
     */
    private function readCsvFile(UploadedFile $file): Reader
    {
        try {
            $csv = Reader::createFromPath($file->getPathname());
            $csv->setDelimiter(';');
            $csv->setHeaderOffset(0);
            return $csv;
        } catch (CsvException | InvalidArgument | UnavailableStream $e) {
            throw new RuntimeException("Kon het CSV-bestand niet correct openen: " . $e->getMessage());
        }
    }

    /**
     * Laadt bestaande transacties uit de database in een lookup-array.
     *
     * Deze methode haalt alle unieke hashes op van transacties die vallen binnen de opgegeven datums.
     * Zo kunnen dubbele records uit de CSV herkend en overgeslagen worden.
     *
     * @param array $dates Een lijst van datumstrings in 'Ymd'-formaat (zoals uit de CSV)
     */
    private function loadExistingTransactions(array $dates): void
    {
        $formattedDates = array_map(function ($d) {
            return DateTime::createFromFormat('Ymd', $d)->format('Y-m-d');
        }, $dates);

        $hashes = $this->transactionRepository->findHashesByDates($formattedDates);

        foreach ($hashes as $hash) {
            $this->existingTransactions[$hash['hash']] = true;
        }
    }

    /**
     * Verwerkt alle rijen uit de CSV en maakt transacties aan.
     *
     * Deze methode:
     * - Valideert en converteert CSV-records naar `Transaction`-entiteiten
     * - Controleert op dubbele transacties via eerder geladen hashes
     * - Houdt bij hoeveel transacties zijn geïmporteerd, overgeslagen of fout zijn gegaan
     * - Voegt geldige transacties toe aan de database
     *
     * @param iterable $records De rijen uit het CSV-bestand
     * @return array Een array met drie elementen:
     *               - int $imported: Aantal succesvol geïmporteerde transacties
     *               - int $skipped: Aantal overgeslagen (duplicate) records
     *               - string[] $errors: Eventuele foutmeldingen per rij
     */
    private function processRecords(iterable $records): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $batch = [];

        foreach ($records as $index => $record) {
            try {
                $transaction = $this->createTransactionFromRecord($record, $index);
                if (!$transaction) {
                    $skipped++;
                    continue;
                }
                $batch[] = $transaction;
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Fout in rij " . ($index + 1) . ": " . $e->getMessage();
                $this->logger->error("CSV Import fout: " . $e->getMessage());
            }
        }

        $this->transactionRepository->saveAll($batch);

        $patternsApplied = $this->applyPatternsToNewTransactions($batch);
        $this->logger->info("Pattern matching voltooid", ['matched' => $patternsApplied]);


        return [$imported, $skipped, $errors];
    }

    /**
     * Extraheert alle unieke datums uit het CSV-bestand.
     *
     * Deze methode verzamelt alle waarden uit de kolom 'Datum', verwijdert duplicaten,
     * en sorteert het resultaat op chronologische volgorde. Deze datums worden gebruikt
     * om bestaande transacties op te zoeken (voor duplicate check).
     *
     * @param iterable $records De rijen uit het CSV-bestand
     * @return array Een gesorteerde lijst van unieke datums (in 'Ymd'-formaat)
     */
    private function extractUniqueDatesFromCsv(iterable $records): array
    {
        $dates = [];
        foreach ($records as $record) {
            if (isset($record[self::FIELD_DATE])) {
                $dates[] = $record[self::FIELD_DATE];
            }
        }

        $dates = array_unique($dates);
        sort($dates);
        return $dates;
    }

    /**
     * Zet één CSV-record om naar een `Transaction`-object.
     *
     * - Valideert vereiste velden
     * - Berekent een unieke hash om duplicates te detecteren
     * - Maakt een nieuwe `Transaction` aan indien deze nog niet bestaat
     *
     * Geeft `null` terug als de transactie al eerder geïmporteerd is.
     *
     * @param array $record De ruwe gegevens van één CSV-regel
     * @param int $index De rij-index (voor foutmeldingen)
     * @return Transaction|null De aangemaakte transactie, of null als deze al bestond
     */
    private function createTransactionFromRecord(array $record, int $index): ?Transaction
    {
        try {
            $this->validateRequiredFields($record, $index);
            $date = $this->parseDate($record[self::FIELD_DATE], $index);
            $amount = $this->convertToMoney($record[self::FIELD_AMOUNT]);
            $balanceAfter = $this->convertToMoney($record[self::FIELD_BALANCE]);
            $transactionType = TransactionType::fromCsvValue($record[self::FIELD_TYPE]);

            // Bereken de unieke hash
            $hash = $this->generateTransactionHash($record);

            // Controleer op bestaande transactie
            if (isset($this->existingTransactions[$hash])) {
                $this->logger->info("Overgeslagen: transactie al bekend", ['hash' => $hash]);
                return null;
            }

            // Voeg toe aan bestaande lijst
            $this->existingTransactions[$hash] = true;

            return $this->createTransactionEntity($record, $date, $transactionType, $amount, $balanceAfter, $hash);
        } catch (Exception $e) {
            throw new BadRequestHttpException("Fout in rij " . ($index + 1) . ": " . $e->getMessage());
        }
    }

    /**
     * Controleert of alle vereiste velden aanwezig zijn in een CSV-record.
     *
     * Als een verplicht veld ontbreekt, wordt een `RuntimeException` gegooid met een duidelijke foutmelding.
     *
     * @param array $record De gegevens van één CSV-regel
     * @param int $index De rij-index voor foutmeldingen (beginnend bij 0)
     * @throws BadRequestHttpException Bij ontbrekende verplichte velden
     */
    private function validateRequiredFields(array $record, int $index): void
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!array_key_exists($field, $record)) {
                throw new BadRequestHttpException("Ontbrekend veld '$field' in rij " . ($index + 1));
            }
        }
    }

    /**
     * Controleert of de header van het CSV-bestand alle verwachte kolomnamen bevat.
     *
     * @param array $header De array met kolomnamen uit de CSV
     * @throws BadRequestHttpException Als een kolom ontbreekt
     */
    private function validateCsvHeader(array $header): void
    {
        $missing = array_diff(self::CSV_LAYOUT, $header);
        if (!empty($missing)) {
            throw new BadRequestHttpException("De CSV mist verplichte kolommen: " . implode(', ', $missing));
        }
    }

    /**
     * Zet een datumstring uit de CSV om naar een `DateTime` object.
     *
     * Verwacht formaat: 'Ymd' (bijv. 20240121). Als de datum ongeldig is,
     * wordt een foutmelding gegenereerd.
     *
     * @param string $dateString De datum als string
     * @param int $index De rij-index voor foutmeldingen
     * @return DateTime Het geconverteerde datumobject
     * @throws BadRequestHttpException Bij een ongeldig datumformaat
     */
    private function parseDate(string $dateString, int $index): DateTime
    {
        $date = DateTime::createFromFormat('Ymd', $dateString);
        if (!$date) {
            throw new BadRequestHttpException("Ongeldige datum in rij " . ($index + 1));
        }
        return $date;
    }

    /**
     * Maakt een nieuwe `Transaction`-entiteit aan op basis van een CSV-record.
     *
     * Stelt alle velden van de transactie in op basis van de meegegeven gegevens.
     * Zorgt ook voor koppeling met een bestaand of nieuw `Account`-object.
     *
     * @param array $record De CSV-gegevens
     * @param DateTime $date De datum van de transactie
     * @param TransactionType $transactionType Type (credit/debit)
     * @param Money $amount Bedrag van de transactie
     * @param Money $balanceAfter Saldo na mutatie
     * @param string $hash Unieke hash voor duplicate-detectie
     * @return Transaction De aangemaakte transactie-entiteit
     */
    private function createTransactionEntity(
        array $record,
        DateTime $date,
        TransactionType $transactionType,
        Money $amount,
        Money $balanceAfter,
        string $hash
    ): Transaction {
        // Haal account op of maak hem aan (en link aan user indien ingesteld)
        if ($this->currentUser) {
            $account = $this->accountService->getOrCreateAccountByNumberForUser(
                $record[self::FIELD_ACCOUNT],
                $this->currentUser
            );
        } else {
            $account = $this->accountService->getOrCreateAccountByNumber($record[self::FIELD_ACCOUNT]);
        }

        $transaction = new Transaction();
        $transaction->setDate($date);
        $transaction->setDescription($record[self::FIELD_DESCRIPTION]);
        $transaction->setAccount($account); // daadwerkelijke relatie
        $transaction->setCounterpartyAccount($record[self::FIELD_COUNTERPARTY] ?? null);
        $transaction->setTransactionCode($record[self::FIELD_CODE] ?? null);
        $transaction->setTransactionType($transactionType);
        $transaction->setAmount($amount);
        $transaction->setMutationType($record[self::FIELD_MUTATION_TYPE] ?? null);
        $transaction->setNotes($record[self::FIELD_NOTES] ?? null);
        $transaction->setBalanceAfter($balanceAfter);
        $transaction->setTag($record[self::FIELD_TAG] ?? null);
        $transaction->setHash($hash);

        return $transaction;
    }

    /**
     * Converteert een getal in CSV-formaat (met komma als decimaalteken) naar een float.
     *
     * Bijvoorbeeld: '123,45' → 123.45
     *
     * @param string $value De invoerwaarde uit de CSV (bijv. '100,50')
     * @return Money De geconverteerde waarde als money object
     */
    private function convertToMoney(string $value): Money
    {
        $normalized = str_replace(',', '.', $value);
        return $this->moneyFactory->fromString($normalized);
    }

    /**
     * Genereert een unieke hash voor een transactie op basis van relevante CSV-velden.
     *
     * Deze hash wordt gebruikt om duplicaten te detecteren tijdens import.
     * Gebruikte velden: Datum, Omschrijving, Rekening, Tegenrekening, Af Bij,
     * Bedrag (EUR), Saldo na mutatie.
     *
     * @param array $record De CSV-record als associatieve array
     * @return string De SHA-256 hash van de samengevoegde gegevens
     */
    private function generateTransactionHash(array $record): string
    {
        $data = [];

        foreach (self::HASH_FIELDS as $field) {
            $value = $record[$field] ?? '';
            if (in_array($field, [self::FIELD_AMOUNT, self::FIELD_BALANCE])) {
                // Maak gebruik van MoneyFactory voor consistente normalisatie
                $normalized = str_replace(',', '.', $value);
                $money = $this->moneyFactory->fromString($normalized);
                $value = $money->getAmount(); // Bewaar als integer string (bijv. "12345")
            }
            $data[] = $value;
        }

        return hash('sha256', implode('|', $data));
    }

    /**
     * Genereert de API-response na het importeren van een CSV-bestand met transacties.
     *
     * Deze methode geeft een gestructureerde array terug met informatie over de verwerkte gegevens:
     * - Het aantal succesvol geïmporteerde transacties
     * - Het aantal overgeslagen transacties (vaak duplicaten)
     * - Eventuele validatiefouten per rij in het CSV-bestand
     * - Optioneel: resultaat van automatische toewijzing aan spaarrekeningen via patroonherkenning
     *
     * Daarnaast wordt het importresultaat gelogd voor monitoring en debuggingdoeleinden.
     *
     * @param int $imported Aantal nieuw toegevoegde transacties
     * @param int $skipped Aantal overgeslagen transacties (bijv. al bestaand)
     * @param array $errors Rij-specifieke fouten bij het verwerken van de CSV
     *
     * @return array Gestructureerde data voor JSON-response naar de frontend
     */
    private function generateResponse(int $imported, int $skipped, array $errors): array
    {
        $this->logger->info("CSV import voltooid", [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => count($errors)
        ]);

        return [
            'status' => count($errors) > 0 ? 'warning' : 'success',
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Past automatisch patterns toe op nieuw geïmporteerde transacties.
     *
     * @param Transaction[] $transactions De nieuwe transacties
     * @return int Aantal transacties waarop patterns zijn toegepast
     */
    private function applyPatternsToNewTransactions(array $transactions): int
    {
        if (empty($transactions)) {
            return 0;
        }

        // Verzamel unieke account IDs
        $accountIds = array_unique(array_map(
            fn(Transaction $t) => $t->getAccount()->getId(),
            $transactions
        ));

        $totalMatched = 0;
        foreach ($accountIds as $accountId) {
            $patterns = $this->patternRepository->findByAccountId($accountId);

            foreach ($patterns as $pattern) {
                $matched = $this->patternAssignService->assignSinglePattern($pattern);
                $totalMatched += $matched;
            }
        }

        return $totalMatched;
    }
}