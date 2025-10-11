<?php

namespace App\Pattern\Service;

use App\Entity\Transaction;
use App\Pattern\DTO\CreatePatternDTO;
use App\Transaction\Mapper\TransactionMapper;
use DateTimeImmutable;
use App\Money\MoneyFactory;
use Doctrine\ORM\EntityManagerInterface;

class MatchingPatternService
{
    private MoneyFactory $moneyFactory;
    private EntityManagerInterface $em;
    private TransactionMapper $transactionMapper;

    public function __construct(
        MoneyFactory $moneyFactory,
        EntityManagerInterface $em,
        TransactionMapper $transactionMapper
    ) {
        $this->moneyFactory = $moneyFactory;
        $this->em = $em;
        $this->transactionMapper = $transactionMapper;
    }
    public function findMatchingTransactions(CreatePatternDTO $pattern): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('t')
            ->from(Transaction::class, 't')
            ->join('t.account', 'a')
            ->where('a.id = :accountId')
            ->setParameter('accountId', $pattern->accountId);

        // Filter op datum
        if ($pattern->startDate) {
            $qb->andWhere('t.date >= :startDate')
                ->setParameter('startDate', new DateTimeImmutable($pattern->startDate));
        }

        if ($pattern->endDate) {
            $qb->andWhere('t.date <= :endDate')
                ->setParameter('endDate', new DateTimeImmutable($pattern->endDate));
        }

        // Filter op bedrag
        if ($pattern->minAmount !== null) {
            $qb->andWhere('t.amountInCents >= :minAmount')
                ->setParameter('minAmount', $this->moneyFactory->fromFloat($pattern->minAmount)->getAmount());
        }

        if ($pattern->maxAmount !== null) {
            $qb->andWhere('t.amountInCents <= :maxAmount')
                ->setParameter('maxAmount', $this->moneyFactory->fromFloat($pattern->maxAmount)->getAmount());
        }

        // Filter op type
        if ($pattern->transactionType) {
            $qb->andWhere('t.transaction_type = :transactionType')
                ->setParameter('transactionType', $pattern->transactionType);
        }

        // Filter op description
        if ($pattern->description) {
            $expr = match ($pattern->matchTypeDescription) {
                'LIKE', 'like', null => 't.description LIKE :desc',
                'REGEX', 'regex' => 'REGEXP(t.description, :desc)',
                'EXACT', 'exact' => 't.description = :desc',
            };
            $matchType = strtoupper($pattern->matchTypeDescription ?? 'LIKE');
            $qb->andWhere($expr)
                ->setParameter('desc', $matchType === 'LIKE' ? '%' . $pattern->description . '%' : $pattern->description);
        }

        // Filter op notes
        if ($pattern->notes) {
            $expr = match ($pattern->matchTypeNotes) {
                'LIKE', 'like', null => 't.notes LIKE :notes',
                'REGEX', 'regex' => 'REGEXP(t.notes, :notes)',
                'EXACT', 'exact' => 't.notes = :notes',
            };
            $matchType = strtoupper($pattern->matchTypeNotes ?? 'LIKE');
            $qb->andWhere($expr)
                ->setParameter('notes', $matchType === 'LIKE' ? '%' . $pattern->notes . '%' : $pattern->notes);
        }

        // Filter op tag
        if ($pattern->tag) {
            $qb->andWhere('t.tag = :tag')
                ->setParameter('tag', $pattern->tag);
        }

        $qb->orderBy('t.date', 'DESC')
            ->addOrderBy('t.id', 'DESC');

        // Totaal aantal matches
        $total = (clone $qb)
            ->select('COUNT(t.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        // Paginatie toepassen
        $matches = $qb
            ->getQuery()
            ->getResult();

        $dtoList = array_map(function (Transaction $t) use ($pattern) {
            $dto = $this->transactionMapper->toMatchesDto($t);

            $conflict = false;

            if ($pattern->categoryId && $t->getCategory()?->getId() !== $pattern->categoryId) {
                $conflict = true;
            }

            if ($pattern->savingsAccountId && $t->getSavingsAccount()?->getId() !== $pattern->savingsAccountId) {
                $conflict = true;
            }

            $dto->matchConflict = $conflict;

            return $dto;
        }, $matches);

        return [
            'total' => (int) $total,
            'data' => $dtoList,
        ];
    }
}