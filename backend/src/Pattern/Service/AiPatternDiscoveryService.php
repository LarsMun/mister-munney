<?php

namespace App\Pattern\Service;

use App\Entity\Transaction;
use App\Pattern\DTO\PatternSuggestionDTO;
use OpenAI;
use Psr\Log\LoggerInterface;

class AiPatternDiscoveryService
{
    private const MAX_TRANSACTIONS_FOR_ANALYSIS = 1000;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey
    ) {
    }

    /**
     * Analyze uncategorized transactions and discover patterns
     *
     * @param Transaction[] $transactions
     * @return PatternSuggestionDTO[]
     */
    public function discoverPatterns(array $transactions): array
    {
        if (empty($transactions)) {
            return [];
        }

        // Limit aantal transactions voor analyse
        $transactions = array_slice($transactions, 0, self::MAX_TRANSACTIONS_FOR_ANALYSIS);

        $client = OpenAI::client($this->openaiApiKey);

        try {
            $prompt = $this->buildPrompt($transactions);

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

            return $this->mapPatterns($result['patterns'], $transactions);

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
    private function buildPrompt(array $transactions): string
    {
        $transactionList = array_map(fn(Transaction $t) => [
            'id' => $t->getId(),
            'description' => $t->getDescription(),
            'amount' => $t->getAmount() ? (float) $t->getAmount()->getAmount() / 100 : 0,
            'type' => $t->getTransactionType()->value,
            'counterparty' => $t->getCounterpartyAccount(),
            'mutationType' => $t->getMutationType(),
            'date' => $t->getDate()?->format('Y-m-d')
        ], $transactions);

        return sprintf(
            "Analyseer de volgende ongecategoriseerde banktransacties en ontdek herhalende patronen.\n\n" .
            "Transacties:\n%s\n\n" .
            "Identificeer patronen door:\n" .
            "1. Transacties te groeperen met vergelijkbare omschrijvingen\n" .
            "2. Een pattern string te maken (bijv. 'ALBERT HEIJN' voor alle Albert Heijn transacties)\n" .
            "3. Een passende categorienaam voor te stellen (bijv. 'Boodschappen', 'Vervoer', 'Abonnementen')\n" .
            "4. Te tellen hoeveel transacties bij dit patroon passen\n" .
            "5. Een confidence score te geven (0-1) hoe zeker je bent van dit patroon\n\n" .
            "Regels voor pattern strings:\n" .
            "- Pattern matching gebruikt SQL LIKE met automatische % wildcards aan begin en eind\n" .
            "- Gebruik GEEN wildcards zoals * of % in de pattern string zelf\n" .
            "- Maak patterns specifiek genoeg om niet teveel te matchen (bijv. 'SPOTIFY' niet 'SPOT')\n" .
            "- Maak patterns breed genoeg om variaties te vangen (bijv. 'ALBERT HEIJN' matcht 'ALBERT HEIJN 1234' en 'ALBERT HEIJN AMSTERDAM')\n" .
            "- Gebruik hoofdletters voor consistentie\n" .
            "- Voorbeelden: 'ALBERT HEIJN', 'SPOTIFY', 'NS GROEP', 'ZIGGO'\n\n" .
            "Geef minimaal 3 en maximaal 15 patronen terug, gesorteerd op matchCount (hoogste eerst).\n\n" .
            "Antwoord in dit JSON formaat:\n" .
            '{"patterns": [' .
            '{"patternString": "ALBERT HEIJN", "categoryName": "Boodschappen", "matchCount": 25, "exampleTransactionIds": [1, 5, 12], "confidence": 0.95, "reasoning": "Supermarkt aankopen"}' .
            ']}',
            json_encode($transactionList, JSON_PRETTY_PRINT)
        );
    }

    /**
     * @param Transaction[] $transactions
     * @return PatternSuggestionDTO[]
     */
    private function mapPatterns(array $aiPatterns, array $transactions): array
    {
        $transactionMap = [];
        foreach ($transactions as $transaction) {
            $transactionMap[$transaction->getId()] = $transaction;
        }

        $suggestions = [];

        foreach ($aiPatterns as $pattern) {
            $patternString = $pattern['patternString'] ?? null;
            $categoryName = $pattern['categoryName'] ?? null;
            $matchCount = (int) ($pattern['matchCount'] ?? 0);
            $exampleIds = $pattern['exampleTransactionIds'] ?? [];
            $confidence = (float) ($pattern['confidence'] ?? 0.0);
            $reasoning = $pattern['reasoning'] ?? '';

            if (!$patternString || !$categoryName) {
                continue;
            }

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
                patternString: $patternString,
                suggestedCategoryName: $categoryName,
                existingCategoryId: null, // We could check if category already exists
                matchCount: $matchCount,
                exampleTransactions: $exampleTransactions,
                confidence: $confidence,
                reasoning: $reasoning
            );
        }

        return $suggestions;
    }
}
