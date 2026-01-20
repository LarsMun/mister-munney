<?php

namespace App\RecurringTransaction\Mapper;

use App\Entity\RecurringTransaction;
use App\RecurringTransaction\DTO\RecurringTransactionDTO;
use App\RecurringTransaction\DTO\UpcomingTransactionDTO;
use DateTimeImmutable;

class RecurringTransactionMapper
{
    public function toDto(RecurringTransaction $entity): RecurringTransactionDTO
    {
        $dto = new RecurringTransactionDTO();

        $dto->id = $entity->getId();
        $dto->accountId = $entity->getAccount()->getId();
        $dto->merchantPattern = $entity->getMerchantPattern();
        $dto->displayName = $entity->getDisplayName();
        $dto->predictedAmount = $entity->getPredictedAmountInCents() / 100;
        $dto->amountVariance = $entity->getAmountVariance();
        $dto->frequency = $entity->getFrequency()->value;
        $dto->frequencyLabel = $entity->getFrequency()->getLabel();
        $dto->confidenceScore = $entity->getConfidenceScore();
        $dto->lastOccurrence = $entity->getLastOccurrence()?->format('Y-m-d');
        $dto->nextExpected = $entity->getNextExpected()?->format('Y-m-d');
        $dto->isActive = $entity->isActive();
        $dto->occurrenceCount = $entity->getOccurrenceCount();
        $dto->intervalConsistency = $entity->getIntervalConsistency();
        $dto->transactionType = $entity->getTransactionType()->value;

        $category = $entity->getCategory();
        $dto->categoryId = $category?->getId();
        $dto->categoryName = $category?->getName();
        $dto->categoryColor = $category?->getColor();

        $dto->createdAt = $entity->getCreatedAt()->format('c');
        $dto->updatedAt = $entity->getUpdatedAt()->format('c');

        return $dto;
    }

    public function toUpcomingDto(RecurringTransaction $entity): UpcomingTransactionDTO
    {
        $dto = new UpcomingTransactionDTO();

        $dto->id = $entity->getId();
        $dto->displayName = $entity->getDisplayName();
        $dto->predictedAmount = $entity->getPredictedAmountInCents() / 100;
        $dto->expectedDate = $entity->getNextExpected()?->format('Y-m-d') ?? '';
        $dto->transactionType = $entity->getTransactionType()->value;
        $dto->frequency = $entity->getFrequency()->value;

        // Calculate days until
        $now = new DateTimeImmutable();
        $nextExpected = $entity->getNextExpected();
        if ($nextExpected) {
            $diff = $now->diff($nextExpected);
            $dto->daysUntil = $diff->invert ? -$diff->days : $diff->days;
        } else {
            $dto->daysUntil = 0;
        }

        $category = $entity->getCategory();
        $dto->categoryColor = $category?->getColor();
        $dto->categoryName = $category?->getName();

        return $dto;
    }

    /**
     * @param RecurringTransaction[] $entities
     * @return RecurringTransactionDTO[]
     */
    public function toDtoList(array $entities): array
    {
        return array_map(fn($e) => $this->toDto($e), $entities);
    }

    /**
     * @param RecurringTransaction[] $entities
     * @return UpcomingTransactionDTO[]
     */
    public function toUpcomingDtoList(array $entities): array
    {
        return array_map(fn($e) => $this->toUpcomingDto($e), $entities);
    }
}
