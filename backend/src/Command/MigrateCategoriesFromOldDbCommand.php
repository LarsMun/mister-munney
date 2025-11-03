<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:migrate-categories-from-old-db',
    description: 'Migreert categorietoekenningen van de oude geld-app naar Munney',
)]
class MigrateCategoriesFromOldDbCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private SymfonyStyle $io;
    private array $stats = [
        'total_transactions' => 0,
        'transactions_without_category' => 0,
        'matched' => 0,
        'updated' => 0,
        'not_found_in_old_db' => 0,
        'no_category_in_old_db' => 0,
        'category_not_found_in_munney' => 0,
        'created_categories' => 0,
    ];

    private array $createdCategoriesCache = [];

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function configure(): void
    {
        $this
            ->addOption('sql-file', null, InputOption::VALUE_REQUIRED, 'Pad naar de SQL dump van de oude database', 'old_db/geld-2025-10-31.sql')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Voer een dry-run uit zonder wijzigingen door te voeren')
            ->addOption('create-categories', null, InputOption::VALUE_NONE, 'Maak automatisch ontbrekende categorieën aan')
            ->addOption('source-account-number', null, InputOption::VALUE_REQUIRED, 'Rekeningnummer van het account uit de oude database (bijv. NL51INGB0665746962)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $sqlFile = $input->getOption('sql-file');
        $dryRun = $input->getOption('dry-run');
        $createCategories = $input->getOption('create-categories');
        $sourceAccountNumber = $input->getOption('source-account-number');

        // Controleer of het bestand bestaat
        if (!file_exists($sqlFile)) {
            $this->io->error("SQL bestand niet gevonden: $sqlFile");
            return Command::FAILURE;
        }

        // Valideer en zoek het source account op basis van rekeningnummer
        $sourceAccount = null;
        if ($sourceAccountNumber) {
            $sourceAccount = $this->entityManager->getRepository(\App\Entity\Account::class)
                ->findOneBy(['accountNumber' => $sourceAccountNumber]);

            if (!$sourceAccount) {
                $this->io->error(sprintf('Account met rekeningnummer %s niet gevonden', $sourceAccountNumber));
                return Command::FAILURE;
            }

            $this->io->note(sprintf(
                'Alleen transacties van account "%s" (%s) worden verwerkt',
                $sourceAccount->getName(),
                $sourceAccountNumber
            ));
        } else {
            $this->io->warning('Geen --source-account-number opgegeven: ALLE accounts worden verwerkt');
            $this->io->warning('Dit kan leiden tot categorieën bij het verkeerde account!');

            if (!$this->io->confirm('Wil je doorgaan zonder account filter?', false)) {
                return Command::FAILURE;
            }
        }

        if ($createCategories) {
            $this->io->note('Ontbrekende categorieën worden automatisch aangemaakt');
        }

        $this->io->title('Migratie van categorietoekenningen van oude database');

        if ($dryRun) {
            $this->io->note('DRY-RUN modus: er worden geen wijzigingen doorgevoerd');
        }

        // Stap 1: Parse de oude database
        $this->io->section('Stap 1: Parse oude database');
        $oldData = $this->parseOldDatabase($sqlFile);

        $this->io->success(sprintf(
            'Oude database geparsed: %d transacties, %d categorieën, %d categorie-toekenningen',
            count($oldData['mutaties']),
            count($oldData['categories']),
            count($oldData['categorie_mutatie'])
        ));

        // Stap 2: Match en update transacties
        $this->io->section('Stap 2: Match en update transacties');
        $this->matchAndUpdateTransactions($oldData, $dryRun, $createCategories, $sourceAccount);

        // Toon statistieken
        $this->io->section('Statistieken');

        $statsTable = [
            ['Totaal aantal transacties in Munney', $this->stats['total_transactions']],
            ['Transacties zonder categorie', $this->stats['transactions_without_category']],
            ['Gematcht met oude database', $this->stats['matched']],
            ['Categorieën toegewezen', $this->stats['updated']],
            ['Niet gevonden in oude database', $this->stats['not_found_in_old_db']],
            ['Geen categorie in oude database', $this->stats['no_category_in_old_db']],
            ['Categorie niet gevonden in Munney', $this->stats['category_not_found_in_munney']],
        ];

        if ($createCategories) {
            $statsTable[] = ['Nieuwe categorieën aangemaakt', $this->stats['created_categories']];
        }

        $this->io->table(['Statistiek', 'Aantal'], $statsTable);

        if ($dryRun) {
            $this->io->warning('DRY-RUN: Er zijn geen wijzigingen doorgevoerd');
        } else {
            $this->io->success(sprintf('Migratie succesvol afgerond: %d categorieën toegewezen', $this->stats['updated']));
        }

        return Command::SUCCESS;
    }

    private function parseOldDatabase(string $sqlFile): array
    {
        $data = [
            'mutaties' => [],
            'categories' => [],
            'categorie_mutatie' => [],
        ];

        // Parse mutaties - gebruik lijn-per-lijn parsing voor betere robuustheid
        $handle = fopen($sqlFile, 'r');
        $inMutatiesSection = false;
        $currentLine = '';

        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, 'INSERT INTO `mutaties` VALUES')) {
                $inMutatiesSection = true;
                continue;
            }

            if ($inMutatiesSection) {
                $currentLine .= $line;

                // Check of we een complete rij hebben (eindigt op );)
                if (str_ends_with(trim($line), ');')) {
                    $inMutatiesSection = false;
                    // Parse alle rijen in currentLine
                    $this->parseMutatiesBlock($currentLine, $data);
                    $currentLine = '';
                } elseif (str_ends_with(trim($line), ',')) {
                    // Continue met de volgende lijn
                    continue;
                } elseif (str_contains($line, 'UNLOCK TABLES') || str_contains($line, 'CREATE TABLE')) {
                    // Einde van de sectie
                    $inMutatiesSection = false;
                    if ($currentLine) {
                        $this->parseMutatiesBlock($currentLine, $data);
                    }
                    $currentLine = '';
                }
            }
        }

        fclose($handle);

        // Parse categories en categorie_mutatie met de oude methode (die werkt)
        $content = file_get_contents($sqlFile);

        // Parse categories
        if (preg_match('/INSERT INTO `categories` VALUES\s*(.*?);/s', $content, $matches)) {
            $values = $matches[1];
            preg_match_all('/\((\d+),\'([^\']*)\',\'([^\']*)\',(?:(\d+)|NULL)\)/', $values, $categories, PREG_SET_ORDER);

            foreach ($categories as $category) {
                $data['categories'][$category[1]] = [
                    'id' => $category[1],
                    'catname' => $category[2],
                    'catcolor' => $category[3],
                    'parentid' => $category[4] ?? null,
                ];
            }
        }

        // Parse categorie_mutatie
        if (preg_match('/INSERT INTO `categorie_mutatie` VALUES\s*(.*?);/s', $content, $matches)) {
            $values = $matches[1];
            preg_match_all('/\((\d+),(\d+),(\d+)\)/', $values, $mappings, PREG_SET_ORDER);

            foreach ($mappings as $mapping) {
                $data['categorie_mutatie'][$mapping[3]] = [ // mutatieid als key
                    'id' => $mapping[1],
                    'catid' => $mapping[2],
                    'mutatieid' => $mapping[3],
                ];
            }
        }

        return $data;
    }

    private function parseMutatiesBlock(string $block, array &$data): void
    {
        // Split het block in individuele rijen
        // Elke rij begint met ( en eindigt met ), of );
        $rows = [];
        $currentRow = '';
        $depth = 0;
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($block); $i++) {
            $char = $block[$i];

            if ($escaped) {
                $currentRow .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $currentRow .= $char;
                $escaped = true;
                continue;
            }

            if ($char === "'" && !$escaped) {
                $inString = !$inString;
                $currentRow .= $char;
                continue;
            }

            if (!$inString) {
                if ($char === '(') {
                    $depth++;
                    if ($depth === 1 && $currentRow) {
                        // Start van een nieuwe rij
                        $currentRow = $char;
                    } else {
                        $currentRow .= $char;
                    }
                } elseif ($char === ')') {
                    $currentRow .= $char;
                    $depth--;
                    if ($depth === 0) {
                        // Einde van een rij
                        $rows[] = $currentRow;
                        $currentRow = '';
                    }
                } else {
                    $currentRow .= $char;
                }
            } else {
                $currentRow .= $char;
            }
        }

        // Parse elke rij
        foreach ($rows as $row) {
            $mutatie = $this->parseMutatieRow($row);
            if ($mutatie) {
                $data['mutaties'][$mutatie['ID']] = $mutatie;
            }
        }
    }

    private function parseMutatieRow(string $row): ?array
    {
        // Verwijder de haakjes
        $row = trim($row, '(),; ');

        // Parse de velden handmatig
        $fields = [];
        $current = '';
        $inString = false;
        $escaped = false;

        for ($i = 0; $i < strlen($row); $i++) {
            $char = $row[$i];

            if ($escaped) {
                $current .= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                continue;
            }

            if ($char === "'") {
                $inString = !$inString;
                continue;
            }

            if ($char === ',' && !$inString) {
                $fields[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Voeg het laatste veld toe
        if ($current !== '') {
            $fields[] = $current;
        }

        // Controleer of we het juiste aantal velden hebben (12 voor mutaties)
        if (count($fields) < 12) {
            return null;
        }

        return [
            'ID' => trim($fields[0]),
            'datum' => trim($fields[1]),
            'omschrijving' => trim($fields[2]),
            'rekening' => trim($fields[3]),
            'tegenrekening' => trim($fields[4]),
            'code' => trim($fields[5]) === 'NULL' ? null : trim($fields[5]),
            'afbij' => trim($fields[6]),
            'bedrag' => trim($fields[7]),
            'mutatiesoort' => trim($fields[8]) === 'NULL' ? null : trim($fields[8]),
            'mededelingen' => trim($fields[9]),
            'saldo_na_mutatie' => trim($fields[10]),
            'tag' => trim($fields[11]) === 'NULL' ? null : trim($fields[11]),
        ];
    }

    private function matchAndUpdateTransactions(array $oldData, bool $dryRun, bool $createCategories, ?\App\Entity\Account $sourceAccount): void
    {
        // Haal transacties op (gefilterd op sourceAccount indien opgegeven)
        if ($sourceAccount !== null) {
            $transactions = $this->entityManager->getRepository(Transaction::class)
                ->findBy(['account' => $sourceAccount]);
            $this->io->note(sprintf('Verwerk %d transacties van account %s', count($transactions), $sourceAccount->getName()));
        } else {
            $transactions = $this->entityManager->getRepository(Transaction::class)->findAll();
            $this->io->note(sprintf('Verwerk %d transacties van ALLE accounts', count($transactions)));
        }

        $this->stats['total_transactions'] = count($transactions);

        $progressBar = $this->io->createProgressBar(count($transactions));
        $progressBar->start();

        foreach ($transactions as $transaction) {
            $progressBar->advance();

            // Sla transacties over die al een categorie hebben
            if ($transaction->getCategory() !== null) {
                continue;
            }

            $this->stats['transactions_without_category']++;

            // Probeer de transactie te matchen in de oude database
            $oldTransaction = $this->findMatchingOldTransaction($transaction, $oldData['mutaties']);

            if ($oldTransaction === null) {
                $this->stats['not_found_in_old_db']++;
                continue;
            }

            $this->stats['matched']++;

            // Check of de oude transactie een categorie had
            $oldMutatieId = $oldTransaction['ID'];
            if (!isset($oldData['categorie_mutatie'][$oldMutatieId])) {
                $this->stats['no_category_in_old_db']++;
                continue;
            }

            // Haal de categorie op
            $oldCatId = $oldData['categorie_mutatie'][$oldMutatieId]['catid'];
            if (!isset($oldData['categories'][$oldCatId])) {
                continue;
            }

            $oldCategoryName = $oldData['categories'][$oldCatId]['catname'];
            $oldCategoryColor = $oldData['categories'][$oldCatId]['catcolor'];

            // Zoek de overeenkomstige categorie in Munney (of maak aan indien nodig)
            // Categorieën worden altijd aangemaakt bij het account van de transactie zelf
            $targetAccount = $transaction->getAccount();
            $category = $this->findOrCreateCategory($oldCategoryName, $oldCategoryColor, $targetAccount, $createCategories, $dryRun);

            if ($category === null) {
                $this->stats['category_not_found_in_munney']++;
                if (!$createCategories) {
                    $this->io->writeln(sprintf(
                        "\n<comment>Categorie niet gevonden in Munney: %s (oude ID: %d)</comment>",
                        $oldCategoryName,
                        $oldCatId
                    ));
                }
                continue;
            }

            // Update de transactie
            if (!$dryRun) {
                $transaction->setCategory($category);
                $this->entityManager->persist($transaction);
            }

            $this->stats['updated']++;
        }

        $progressBar->finish();
        $this->io->newLine(2);

        if (!$dryRun) {
            $this->entityManager->flush();
        }
    }

    private function findMatchingOldTransaction(Transaction $transaction, array $oldMutaties): ?array
    {
        $description = $transaction->getDescription();
        $notes = $transaction->getNotes() ?? '';
        $date = $transaction->getDate()->format('Y-m-d');
        $amount = abs($transaction->getAmount()->getAmount() / 100); // Converteer cents naar euros

        // Zoek in oude mutaties op basis van datum + bedrag + omschrijving
        foreach ($oldMutaties as $mutatie) {
            // Match op datum en bedrag
            if ($mutatie['datum'] !== $date) {
                continue;
            }

            $oldAmount = (float)$mutatie['bedrag'];
            if (abs($oldAmount - $amount) > 0.01) { // Kleine tolerantie voor rounding errors
                continue;
            }

            // Match op omschrijving (exacte match of substring)
            $oldDescription = $mutatie['omschrijving'];
            $oldNotes = $mutatie['mededelingen'];

            // Probeer verschillende match strategieën
            if ($description === $oldDescription) {
                return $mutatie;
            }

            // Check of description + notes matcht met omschrijving + mededelingen
            $newCombined = $description . ' ' . $notes;
            $oldCombined = $oldDescription . ' ' . $oldNotes;

            if (strtolower(trim($newCombined)) === strtolower(trim($oldCombined))) {
                return $mutatie;
            }

            // Check substring match (voor het geval er kleine verschillen zijn)
            if (strlen($description) > 10 && stripos($oldDescription, $description) !== false) {
                return $mutatie;
            }
        }

        return null;
    }

    private function findOrCreateCategory(string $name, string $color, \App\Entity\Account $account, bool $createIfNotExists, bool $dryRun): ?Category
    {
        // Check cache first (voor categorieën die we al hebben aangemaakt)
        $cacheKey = $account->getId() . '_' . strtolower($name);
        if (isset($this->createdCategoriesCache[$cacheKey])) {
            return $this->createdCategoriesCache[$cacheKey];
        }

        // Probeer exacte match
        $category = $this->entityManager->getRepository(Category::class)
            ->findOneBy(['name' => $name, 'account' => $account]);

        if ($category !== null) {
            $this->createdCategoriesCache[$cacheKey] = $category;
            return $category;
        }

        // Probeer case-insensitive match
        $categories = $this->entityManager->getRepository(Category::class)
            ->findBy(['account' => $account]);

        foreach ($categories as $category) {
            if (strtolower($category->getName()) === strtolower($name)) {
                $this->createdCategoriesCache[$cacheKey] = $category;
                return $category;
            }
        }

        // Categorie niet gevonden - maak aan indien nodig
        if (!$createIfNotExists) {
            return null;
        }

        // Maak de categorie aan
        if ($dryRun) {
            $this->io->writeln(sprintf(
                "\n<info>Zou categorie aanmaken: %s (kleur: %s)</info>",
                $name,
                $color
            ));
            $this->stats['created_categories']++;
            // Return een dummy category voor dry-run (zodat we de statistieken correct kunnen tellen)
            $dummyCategory = new Category();
            $dummyCategory->setName($name);
            $this->createdCategoriesCache[$cacheKey] = $dummyCategory;
            return $dummyCategory;
        }

        // Maak daadwerkelijk de categorie aan
        $newCategory = new Category();
        $newCategory->setName($name);
        $newCategory->setColor($color);
        $newCategory->setAccount($account);

        $this->entityManager->persist($newCategory);
        $this->stats['created_categories']++;

        $this->io->writeln(sprintf(
            "\n<info>Categorie aangemaakt: %s (kleur: %s)</info>",
            $name,
            $color
        ));

        $this->createdCategoriesCache[$cacheKey] = $newCategory;

        return $newCategory;
    }
}
