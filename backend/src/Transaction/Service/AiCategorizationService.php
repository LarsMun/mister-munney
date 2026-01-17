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
        $this->logger->info('AI categorization started', [
            'accountId' => $accountId,
            'transactionCount' => count($transactions)
        ]);

        if (empty($transactions)) {
            $this->logger->info('No transactions to categorize');
            return [];
        }

        $categories = $this->categoryRepository->findBy(['account' => $accountId]);

        $this->logger->info('Categories fetched', [
            'categoriesType' => gettype($categories),
            'categoriesCount' => is_array($categories) ? count($categories) : 'N/A'
        ]);

        if (!is_array($categories) || empty($categories)) {
            $this->logger->warning('No categories found or invalid categories response', [
                'categoriesType' => gettype($categories)
            ]);
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

        try {
            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => 'Je bent een expert in het categoriseren van banktransacties. Analyseer de transacties en wijs de meest passende categorie toe. Antwoord alleen met geldige JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object']
            ]);
        } catch (\Exception $e) {
            $this->logger->error('OpenAI API call failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            throw new \RuntimeException('OpenAI API call failed: ' . $e->getMessage());
        }

        if (!isset($response->choices[0])) {
            $this->logger->error('OpenAI response missing choices', [
                'response' => json_encode($response)
            ]);
            throw new \RuntimeException('OpenAI returned invalid response (no choices)');
        }

        $content = $response->choices[0]->message->content;
        $result = json_decode($content, true);

        if ($result === null) {
            $this->logger->error('Failed to parse OpenAI JSON response', [
                'content' => $content
            ]);
            throw new \RuntimeException('Failed to parse AI response as JSON');
        }

        if (!isset($result['suggestions']) || !is_array($result['suggestions'])) {
            $this->logger->error('Invalid AI response format', [
                'result' => $result
            ]);
            throw new \RuntimeException('Invalid AI response format (missing suggestions array)');
        }

        return $this->mapSuggestions($result['suggestions'], $transactions, $categories);
    }

    /**
     * @param Transaction[] $transactions
     * @param Category[] $categories
     */
    private function buildPrompt(array $transactions, array $categories): string
    {
        // Defensive null checks with logging
        if (!is_array($categories)) {
            $this->logger->error('buildPrompt received non-array categories', [
                'type' => gettype($categories)
            ]);
            throw new \RuntimeException('Categories must be an array, got: ' . gettype($categories));
        }

        if (!is_array($transactions)) {
            $this->logger->error('buildPrompt received non-array transactions', [
                'type' => gettype($transactions)
            ]);
            throw new \RuntimeException('Transactions must be an array, got: ' . gettype($transactions));
        }

        $categoryList = array_map(fn(Category $c) => [
            'id' => $c->getId(),
            'name' => $c->getName()
        ], $categories);

        $transactionList = array_map(fn(Transaction $t) => [
            'id' => $t->getId(),
            'description' => $t->getDescription(),
            'amount' => $t->getAmount() ? (float) $t->getAmount()->getAmount() / 100 : 0,
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
