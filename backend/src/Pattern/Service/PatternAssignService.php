<?php

namespace App\Pattern\Service;

use App\Entity\Pattern;
use App\Pattern\DTO\AssignPatternDateRangeDTO;
use App\Transaction\Repository\TransactionRepository;
use App\Pattern\Repository\PatternRepository;
use DateTimeImmutable;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use App\Enum\MatchType;
use Doctrine\DBAL\Connection;

class PatternAssignService
{
    private TransactionRepository $transactionRepository;
    private PatternRepository $patternRepository;
    private Connection $connection;

    public function __construct(
        TransactionRepository $transactionRepository,
        PatternRepository $patternRepository,
        Connection $connection
    ) {
        $this->transactionRepository = $transactionRepository;
        $this->patternRepository = $patternRepository;
        $this->connection = $connection;
    }

    public function assignSinglePattern(Pattern $pattern): int
    {
        return $this->assignPatternViaSql($pattern);
    }

    public function assign(int $accountId, AssignPatternDateRangeDTO $dto): int
    {
        try {
            $startDate = new DateTimeImmutable($dto->startDate);
            $endDate = new DateTimeImmutable($dto->endDate);
        } catch (Exception $e) {
            throw new BadRequestHttpException('Ongeldige datuminvoer: ' . $e->getMessage());
        }

        $patterns = $this->patternRepository->findByAccountId($accountId);
        $updatedCount = 0;

        foreach ($patterns as $pattern) {
            $pattern->setStartDate($startDate);
            $pattern->setEndDate($endDate);
            $updatedCount += $this->assignPatternViaSql($pattern);
        }

        return $updatedCount;
    }

    private function assignPatternViaSql(Pattern $pattern): int
    {
        $set = [];
        $params = [];

        if ($pattern->getCategory()) {
            $set[] = 'category_id = :categoryId';
            $params['categoryId'] = $pattern->getCategory()->getId();
        }

        if ($pattern->getSavingsAccount()) {
            $set[] = 'savings_account_id = :savingsAccountId';
            $params['savingsAccountId'] = $pattern->getSavingsAccount()->getId();
        }

        if (empty($set)) {
            return 0;
        }

        $where = ['account_id = :accountId'];
        $params['accountId'] = $pattern->getAccount()->getId();

        if ($pattern->getStartDate()) {
            $where[] = 'date >= :startDate';
            $params['startDate'] = $pattern->getStartDate()->format('Y-m-d');
        }

        if ($pattern->getEndDate()) {
            $where[] = 'date <= :endDate';
            $params['endDate'] = $pattern->getEndDate()->format('Y-m-d');
        }

        if ($pattern->getMinAmount()) {
            $where[] = 'amount >= :minAmount';
            $params['minAmount'] = $pattern->getMinAmount()->getAmount();
        }

        if ($pattern->getMaxAmount()) {
            $where[] = 'amount <= :maxAmount';
            $params['maxAmount'] = $pattern->getMaxAmount()->getAmount();
        }

        if ($pattern->getTransactionType()) {
            $where[] = 'transaction_type = :transactionType';
            $params['transactionType'] = $pattern->getTransactionType()->value;
        }

        if ($pattern->getDescription()) {
            $where[] = $this->buildTextCondition('description', $pattern->getDescription(), $pattern->getMatchTypeDescription(), $params);
        }

        if ($pattern->getNotes()) {
            $where[] = $this->buildTextCondition('notes', $pattern->getNotes(), $pattern->getMatchTypeNotes(), $params);
        }

        if ($pattern->getTag()) {
            $where[] = 'tag = :tag';
            $params['tag'] = $pattern->getTag();
        }

        if (!$pattern->isStrict()) {
            if ($pattern->getCategory()) {
                $where[] = 'category_id IS NULL';
            }
            if ($pattern->getSavingsAccount()) {
                $where[] = 'savings_account_id IS NULL';
            }
        }

        $sql = sprintf(
            'UPDATE transaction SET %s WHERE %s',
            implode(', ', $set),
            implode(' AND ', $where)
        );

        return $this->connection->executeStatement($sql, $params);
    }

    private function buildTextCondition(string $field, string $value, MatchType $matchType, array &$params): string
    {
        $paramName = $field . '_filter';

        if ($matchType === MatchType::LIKE) {
            $params[$paramName] = '%' . $value . '%';
            return "$field LIKE :$paramName";
        }

        if ($matchType === MatchType::EXACT) {
            $params[$paramName] = $value;
            return "$field = :$paramName";
        }

        throw new \RuntimeException('Unsupported match type');
    }
}