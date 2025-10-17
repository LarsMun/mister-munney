<?php

namespace App\Pattern\Service;

use App\Entity\AiPatternSuggestion;
use App\Entity\Account;
use App\Entity\Category;
use App\Entity\Transaction;
use App\Enum\AiPatternSuggestionStatus;
use App\Pattern\DTO\PatternSuggestionDTO;
use App\Pattern\Repository\AiPatternSuggestionRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenAI;
use Psr\Log\LoggerInterface;

class AiPatternDiscoveryService
{
    private const MAX_TRANSACTIONS_FOR_ANALYSIS = 200;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey,
        private readonly AiPatternSuggestionRepository $suggestionRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Analyze uncategorized transactions and discover patterns
     *
     * @param Transaction[] $transactions
     * @return PatternSuggestionDTO[]
     */
    public function discoverPatterns(array $transactions, int $accountId): array
    {
        if (empty($transactions)) {
            return [];
        }

        // Get account entity
        $account = $this->entityManager->getRepository(Account::class)->find($accountId);
        if (!$account) {
            throw new \RuntimeException("Account not found: $accountId");
        }

        // Limit aantal transactions voor analyse
        $transactions = array_slice($transactions, 0, self::MAX_TRANSACTIONS_FOR_ANALYSIS);

        $client = OpenAI::client($this->openaiApiKey);

        try {
            $prompt = $this->buildPrompt($transactions, $account);

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Je bent een expert in het ontdekken van patronen in banktransacties. Analyseer de transacties en identificeer herhalende patronen die gebruikt kunnen worden voor automatische categorisering. Antwoord alleen met geldige JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
                'response_format' => ['type' => 'json_object']
            ]);

            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);

            if (!isset($result['patterns']) || !is_array($result['patterns'])) {
                throw new \RuntimeException('Invalid AI response format');
            }

            $suggestions = $this->mapPatterns($result['patterns'], $transactions, $account);

            // Save new suggestions to database and filter out existing ones
            $suggestions = $this->saveAndFilterSuggestions($suggestions, $account);

            return $suggestions;

        } catch (\Exception $e) {
            $this->logger->error('AI pattern discovery failed', [
                'error' => $e->getMessage(),
                'transaction_count' => count($transactions)
            ]);
            throw $e;
        }
    }

    /**
     * @param Transaction[] $transactions
     */
    private function buildPrompt(array $transactions, Account $account): string
    {
        $transactionList = array_map(fn(Transaction $t) => [
            'id' => $t->getId(),
            'description' => $t->getDescription(),
            'notes' => $t->getNotes(),
            'amount' => $t->getAmount() ? (float) $t->getAmount()->getAmount() / 100 : 0
        ], $transactions);

        $transactionsJson = json_encode($transactionList, JSON_PRETTY_PRINT);

        // Get existing categories for this account
        $categories = $this->entityManager->getRepository(Category::class)
            ->findBy(['account' => $account], ['name' => 'ASC']);

        $categoryNames = array_map(fn(Category $c) => $c->getName(), $categories);
        $categoriesJson = json_encode($categoryNames, JSON_PRETTY_PRINT);

        // Build feedback from previous accepted/rejected patterns
        $feedback = $this->buildFeedbackExamples($account);
        $feedbackSection = $feedback ? "\nLeer van eerdere beslissingen:\n{$feedback}\n" : "";

        return "Ontdek patronen in transacties.\n\n" .
            "Data:\n{$transactionsJson}\n\n" .
            "Bestaande categorieën (gebruik deze alleen bij een duidelijke match):\n{$categoriesJson}" .
            $feedbackSection . "\n" .
            "Regels:\n" .
            "- Match op description (bedrijfsnaam/tegenpartij), notes (extra omschrijving), of beide\n" .
            "- GEEN wildcards\n" .
            "- Zoek patronen in zowel description als notes velden\n" .
            "- BELANGRIJK: Wees specifiek met notes patronen - gebruik volledige identifiers\n" .
            "- VERPLICHT: Als je transacties ziet met dezelfde description maar verschillende:\n" .
            "  * Bedragen (bijv. €22.60 vs €26.85)\n" .
            "  * Polisnummers (bijv. 'Polis 21001645037' vs 'Polis 21001645039')\n" .
            "  * Rekeningnummers (bijv. 'F54892305' vs 'F54892306')\n" .
            "  * Andere identificerende codes\n" .
            "  DAN: maak voor ELKE unieke combinatie een APART patroon\n" .
            "- VERKEERD: notesPattern: 'Polis' (te breed, matched alles)\n" .
            "- GOED: notesPattern: 'Polis 21001645037' (specifiek, matched één ding)\n" .
            "- Hoofdletters\n" .
            "- Gebruik een bestaande categorie ALLEEN als deze semantisch echt past\n" .
            "- Maak een nieuwe categorie als geen bestaande categorie goed past\n" .
            "- Een verkeerde categorisatie is erger dan een nieuwe categorie maken\n" .
            "- Leer van eerdere geaccepteerde en afgewezen patronen\n" .
            "- 5-20 patronen (liever meer specifieke patronen dan minder brede patronen)\n\n" .
            "Format:\n" .
            '{"patterns": [' .
            '{"descriptionPattern": "ALBERT HEIJN", "notesPattern": null, "categoryName": "Boodschappen", "matchCount": 25, "exampleTransactionIds": [1, 5], "confidence": 0.95, "reasoning": "Supermarkt"}' .
            ']}';
    }

    /**
     * Build feedback examples from previously accepted/rejected suggestions
     */
    private function buildFeedbackExamples(Account $account): string
    {
        // Get recent accepted and rejected suggestions (max 20)
        $suggestions = $this->entityManager->getRepository(AiPatternSuggestion::class)
            ->createQueryBuilder('aps')
            ->where('aps.account = :account')
            ->andWhere('aps.status IN (:statuses)')
            ->setParameter('account', $account)
            ->setParameter('statuses', [
                AiPatternSuggestionStatus::ACCEPTED,
                AiPatternSuggestionStatus::ACCEPTED_ALTERED,
                AiPatternSuggestionStatus::REJECTED
            ])
            ->orderBy('aps.processedAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        if (empty($suggestions)) {
            return '';
        }

        $feedbackLines = [];

        foreach ($suggestions as $suggestion) {
            $suggestedPattern = $this->formatPattern(
                $suggestion->getDescriptionPattern(),
                $suggestion->getNotesPattern()
            );

            if ($suggestion->getStatus() === AiPatternSuggestionStatus::REJECTED) {
                // Pattern was rejected
                $feedbackLines[] = sprintf(
                    "❌ AFGEWEZEN: Patroon '%s' → Categorie '%s'",
                    $suggestedPattern,
                    $suggestion->getSuggestedCategoryName()
                );
            } elseif ($suggestion->getStatus() === AiPatternSuggestionStatus::ACCEPTED_ALTERED) {
                // Pattern was accepted with alterations
                $acceptedPattern = $this->formatPattern(
                    $suggestion->getAcceptedDescriptionPattern(),
                    $suggestion->getAcceptedNotesPattern()
                );
                $acceptedCategory = $suggestion->getAcceptedCategoryName();

                $changes = [];
                if ($suggestedPattern !== $acceptedPattern) {
                    $changes[] = "patroon '{$suggestedPattern}' → '{$acceptedPattern}'";
                }
                if ($suggestion->getSuggestedCategoryName() !== $acceptedCategory) {
                    $changes[] = "categorie '{$suggestion->getSuggestedCategoryName()}' → '{$acceptedCategory}'";
                }

                $changeStr = implode(', ', $changes);
                $feedbackLines[] = sprintf(
                    "✏️ AANGEPAST: Je wijzigde %s",
                    $changeStr
                );
            } elseif ($suggestion->getStatus() === AiPatternSuggestionStatus::ACCEPTED) {
                // Pattern was accepted as-is
                $feedbackLines[] = sprintf(
                    "✅ GEACCEPTEERD: Patroon '%s' → Categorie '%s'",
                    $suggestedPattern,
                    $suggestion->getSuggestedCategoryName()
                );
            }
        }

        return implode("\n", $feedbackLines);
    }

    /**
     * Format pattern for display
     */
    private function formatPattern(?string $description, ?string $notes): string
    {
        $parts = array_filter([$description, $notes]);
        return implode(' + ', $parts);
    }

    /**
     * @param Transaction[] $transactions
     * @return PatternSuggestionDTO[]
     */
    private function mapPatterns(array $aiPatterns, array $transactions, Account $account): array
    {
        $transactionMap = [];
        foreach ($transactions as $transaction) {
            $transactionMap[$transaction->getId()] = $transaction;
        }

        // Get existing categories to match against
        $categories = $this->entityManager->getRepository(Category::class)
            ->findBy(['account' => $account]);

        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[strtolower($category->getName())] = $category->getId();
        }

        $suggestions = [];

        foreach ($aiPatterns as $pattern) {
            $descriptionPattern = $pattern['descriptionPattern'] ?? null;
            $notesPattern = $pattern['notesPattern'] ?? null;
            $categoryName = $pattern['categoryName'] ?? null;
            $matchCount = (int) ($pattern['matchCount'] ?? 0);
            $exampleIds = $pattern['exampleTransactionIds'] ?? [];
            $confidence = (float) ($pattern['confidence'] ?? 0.0);
            $reasoning = $pattern['reasoning'] ?? '';

            // Skip if no pattern is provided or no category name
            if ((!$descriptionPattern && !$notesPattern) || !$categoryName) {
                continue;
            }

            // Check if this category name matches an existing category (case-insensitive)
            $existingCategoryId = $categoryMap[strtolower($categoryName)] ?? null;

            // Map example transaction IDs to actual transactions
            $exampleTransactions = [];
            foreach ($exampleIds as $id) {
                if (isset($transactionMap[$id])) {
                    $t = $transactionMap[$id];
                    $exampleTransactions[] = [
                        'id' => $t->getId(),
                        'description' => $t->getDescription(),
                        'amount' => $t->getAmount() ? (float) $t->getAmount()->getAmount() / 100 : 0,
                        'date' => $t->getDate()?->format('Y-m-d')
                    ];
                }
            }

            // Limit to max 5 examples
            $exampleTransactions = array_slice($exampleTransactions, 0, 5);

            $suggestions[] = new PatternSuggestionDTO(
                descriptionPattern: $descriptionPattern,
                notesPattern: $notesPattern,
                suggestedCategoryName: $categoryName,
                existingCategoryId: $existingCategoryId,
                matchCount: $matchCount,
                exampleTransactions: $exampleTransactions,
                confidence: $confidence,
                reasoning: $reasoning
            );
        }

        return $suggestions;
    }

    /**
     * Save suggestions to database and filter out processed ones
     * Pending suggestions are returned again with a flag indicating they were previously discovered
     *
     * @param PatternSuggestionDTO[] $suggestions
     * @return PatternSuggestionDTO[]
     */
    private function saveAndFilterSuggestions(array $suggestions, Account $account): array
    {
        $resultSuggestions = [];

        foreach ($suggestions as $suggestionDTO) {
            // Generate pattern hash
            $patternHash = $this->generatePatternHash(
                $account->getId(),
                $suggestionDTO->descriptionPattern,
                $suggestionDTO->notesPattern
            );

            // Check if this pattern was already processed (accepted/rejected)
            if ($this->suggestionRepository->existsProcessedByPatternHash($account->getId(), $patternHash)) {
                $this->logger->info('Skipping processed pattern suggestion', [
                    'pattern_hash' => $patternHash,
                    'description' => $suggestionDTO->descriptionPattern,
                    'notes' => $suggestionDTO->notesPattern
                ]);
                continue;
            }

            // Check if this is a pending pattern that was discovered before
            $wasPreviouslyDiscovered = $this->suggestionRepository->existsPendingByPatternHash($account->getId(), $patternHash);

            if ($wasPreviouslyDiscovered) {
                $this->logger->info('Re-showing pending pattern suggestion', [
                    'pattern_hash' => $patternHash,
                    'description' => $suggestionDTO->descriptionPattern,
                    'notes' => $suggestionDTO->notesPattern
                ]);
            } else {
                // Create and save new suggestion entity
                $suggestion = new AiPatternSuggestion();
                $suggestion->setAccount($account);
                $suggestion->setDescriptionPattern($suggestionDTO->descriptionPattern);
                $suggestion->setNotesPattern($suggestionDTO->notesPattern);
                $suggestion->setSuggestedCategoryName($suggestionDTO->suggestedCategoryName);

                // Link to existing category if AI suggested one that exists
                if ($suggestionDTO->existingCategoryId) {
                    $existingCategory = $this->entityManager->getRepository(Category::class)
                        ->find($suggestionDTO->existingCategoryId);
                    if ($existingCategory) {
                        $suggestion->setExistingCategory($existingCategory);
                    }
                }

                $suggestion->setMatchCount($suggestionDTO->matchCount);
                $suggestion->setConfidence($suggestionDTO->confidence);
                $suggestion->setReasoning($suggestionDTO->reasoning);
                $suggestion->setExampleTransactions($suggestionDTO->exampleTransactions);
                $suggestion->setPatternHash($patternHash);

                $this->suggestionRepository->save($suggestion);

                $this->logger->info('Saved new pattern suggestion', [
                    'pattern_hash' => $patternHash,
                    'category' => $suggestionDTO->suggestedCategoryName,
                    'existing_category_id' => $suggestionDTO->existingCategoryId,
                    'confidence' => $suggestionDTO->confidence
                ]);
            }

            // Add to results with previouslyDiscovered flag
            $resultSuggestions[] = new PatternSuggestionDTO(
                descriptionPattern: $suggestionDTO->descriptionPattern,
                notesPattern: $suggestionDTO->notesPattern,
                suggestedCategoryName: $suggestionDTO->suggestedCategoryName,
                existingCategoryId: $suggestionDTO->existingCategoryId,
                matchCount: $suggestionDTO->matchCount,
                exampleTransactions: $suggestionDTO->exampleTransactions,
                confidence: $suggestionDTO->confidence,
                reasoning: $suggestionDTO->reasoning,
                previouslyDiscovered: $wasPreviouslyDiscovered
            );
        }

        return $resultSuggestions;
    }

    /**
     * Generate unique hash for pattern
     */
    private function generatePatternHash(int $accountId, ?string $descriptionPattern, ?string $notesPattern): string
    {
        $data = implode('|', [
            $accountId,
            $descriptionPattern ?? '',
            $notesPattern ?? ''
        ]);

        return hash('sha256', $data);
    }
}
