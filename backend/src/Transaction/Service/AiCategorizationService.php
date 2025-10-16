<?php

namespace App\Transaction\Service;

use App\Category\Repository\CategoryRepository;
use App\Entity\Category;
use App\Entity\Transaction;
use OpenAI;
use Psr\Log\LoggerInterface;

class AiCategorizationService
{
    private const MAX_BATCH_SIZE = 20;

    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly string $openaiApiKey
    ) {
    }

    /**
     * Suggest categories for transactions without a category using AI
     *
     * @param Transaction[] $transactions
     * @return array<int, array{transactionId: int, suggestedCategoryId: int|null, confidence: float, reasoning: string}>
     */
    public function suggestCategories(array $transactions, int $accountId): array
    {
        if (empty($transactions)) {
            return [];
        }

        $categories = $this->categoryRepository->findBy(['account' => $accountId]);
        if (empty($categories)) {
            return [];
        }

        $client = OpenAI::client($this->openaiApiKey);

        $suggestions = [];

        // Process in batches to avoid token limits
        $batches = array_chunk($transactions, self::MAX_BATCH_SIZE);

        foreach ($batches as $batch) {
            try {
                $batchSuggestions = $this->processBatch($client, $batch, $categories);
                $suggestions = array_merge($suggestions, $batchSuggestions);
            } catch (\Exception $e) {
                $this->logger->error('AI categorization batch failed', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch)
                ]);

                // Add empty suggestions for failed batch
                foreach ($batch as $transaction) {
                    $suggestions[] = [
                        'transactionId' => $transaction->getId(),
                        'suggestedCategoryId' => null,
                        'confidence' => 0.0,
                        'reasoning' => 'AI service error: ' . $e->getMessage()
                    ];
                }
            }
        }

        return $suggestions;
    }

    /**
     * @param Transaction[] $transactions
     * @param Category[] $categories
     * @return array<int, array{transactionId: int, suggestedCategoryId: int|null, confidence: float, reasoning: string}>
     */
    private function processBatch($client, array $transactions, array $categories): array
    {
        $prompt = $this->buildPrompt($transactions, $categories);

        $response = $client->chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Je bent een expert in het categoriseren van banktransacties. Analyseer de transacties en wijs de meest passende categorie toe. Antwoord alleen met geldige JSON.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'temperature' => 0.3,
            'response_format' => ['type' => 'json_object']
        ]);

        $content = $response->choices[0]->message->content;
        $result = json_decode($content, true);

        if (!isset($result['suggestions']) || !is_array($result['suggestions'])) {
            throw new \RuntimeException('Invalid AI response format');
        }

        return $this->mapSuggestions($result['suggestions'], $transactions, $categories);
    }

    /**
     * @param Transaction[] $transactions
     * @param Category[] $categories
     */
    private function buildPrompt(array $transactions, array $categories): string
    {
        $categoryList = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName(),
            'type' => $c->getTransactionType()->value
        ], $categories);

        $transactionList = array_map(fn(Transaction $t) => [
            'id' => $t->getId(),
            'description' => $t->getDescription(),
            'amount' => (float) $t->getAmount(),
            'type' => $t->getTransactionType()->value,
            'counterparty' => $t->getCounterpartyAccount(),
            'mutationType' => $t->getMutationType()
        ], $transactions);

        return sprintf(
            "Categoriseer de volgende banktransacties.\n\n" .
            "Beschikbare categorieën:\n%s\n\n" .
            "Transacties om te categoriseren:\n%s\n\n" .
            "Geef voor elke transactie:\n" .
            "1. De meest passende categorie ID (of null als geen goede match)\n" .
            "2. Een confidence score tussen 0 en 1\n" .
            "3. Een korte uitleg (max 50 karakters)\n\n" .
            "Antwoord in dit JSON formaat:\n" .
            '{"suggestions": [{"transactionId": 123, "categoryId": 5, "confidence": 0.95, "reasoning": "Supermarkt aankoop"}]}',
            json_encode($categoryList, JSON_PRETTY_PRINT),
            json_encode($transactionList, JSON_PRETTY_PRINT)
        );
    }

    /**
     * @param Category[] $categories
     * @param Transaction[] $transactions
     */
    private function mapSuggestions(array $aiSuggestions, array $transactions, array $categories): array
    {
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getId()] = $category;
        }

        $transactionMap = [];
        foreach ($transactions as $transaction) {
            $transactionMap[$transaction->getId()] = $transaction;
        }

        $mappedSuggestions = [];

        foreach ($aiSuggestions as $suggestion) {
            $transactionId = $suggestion['transactionId'] ?? null;
            $categoryId = $suggestion['categoryId'] ?? null;
            $confidence = (float) ($suggestion['confidence'] ?? 0.0);
            $reasoning = $suggestion['reasoning'] ?? 'No reasoning provided';

            // Validate transaction exists
            if (!isset($transactionMap[$transactionId])) {
                continue;
            }

            // Validate category exists (if provided)
            if ($categoryId !== null && !isset($categoryMap[$categoryId])) {
                $categoryId = null;
                $confidence = 0.0;
                $reasoning = 'Invalid category suggested';
            }

            $mappedSuggestions[] = [
                'transactionId' => $transactionId,
                'suggestedCategoryId' => $categoryId,
                'confidence' => $confidence,
                'reasoning' => $reasoning
            ];
        }

        return $mappedSuggestions;
    }
}
