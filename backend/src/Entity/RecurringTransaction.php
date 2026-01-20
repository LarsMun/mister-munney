<?php

namespace App\Entity;

use App\Enum\RecurrenceFrequency;
use App\Enum\TransactionType;
use App\RecurringTransaction\Repository\RecurringTransactionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: RecurringTransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class RecurringTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Account $account;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Category $category = null;

    #[ORM\Column(length: 255)]
    private string $merchantPattern;

    #[ORM\Column(length: 255)]
    private string $displayName;

    #[ORM\Column(name: "predicted_amount", type: Types::INTEGER)]
    private int $predictedAmountInCents;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $amountVariance = '0.00';

    #[ORM\Column(type: "string", enumType: RecurrenceFrequency::class)]
    private RecurrenceFrequency $frequency;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2)]
    private string $confidenceScore;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastOccurrence = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $nextExpected = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER)]
    private int $occurrenceCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $intervalConsistency = '0.00';

    #[ORM\Column(type: "string", enumType: TransactionType::class)]
    private TransactionType $transactionType;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccount(): Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getMerchantPattern(): string
    {
        return $this->merchantPattern;
    }

    public function setMerchantPattern(string $merchantPattern): static
    {
        $this->merchantPattern = $merchantPattern;
        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): static
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getPredictedAmount(): Money
    {
        return Money::EUR($this->predictedAmountInCents);
    }

    public function setPredictedAmount(Money $money): static
    {
        $this->predictedAmountInCents = (int) $money->getAmount();
        return $this;
    }

    public function getPredictedAmountInCents(): int
    {
        return $this->predictedAmountInCents;
    }

    public function getAmountVariance(): float
    {
        return (float) $this->amountVariance;
    }

    public function setAmountVariance(float $variance): static
    {
        $this->amountVariance = number_format($variance, 2, '.', '');
        return $this;
    }

    public function getFrequency(): RecurrenceFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(RecurrenceFrequency $frequency): static
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getConfidenceScore(): float
    {
        return (float) $this->confidenceScore;
    }

    public function setConfidenceScore(float $score): static
    {
        $this->confidenceScore = number_format(min(1.0, max(0.0, $score)), 2, '.', '');
        return $this;
    }

    public function getLastOccurrence(): ?DateTimeImmutable
    {
        return $this->lastOccurrence;
    }

    public function setLastOccurrence(?DateTimeImmutable $date): static
    {
        $this->lastOccurrence = $date;
        return $this;
    }

    public function getNextExpected(): ?DateTimeImmutable
    {
        return $this->nextExpected;
    }

    public function setNextExpected(?DateTimeImmutable $date): static
    {
        $this->nextExpected = $date;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getOccurrenceCount(): int
    {
        return $this->occurrenceCount;
    }

    public function setOccurrenceCount(int $count): static
    {
        $this->occurrenceCount = $count;
        return $this;
    }

    public function getIntervalConsistency(): float
    {
        return (float) $this->intervalConsistency;
    }

    public function setIntervalConsistency(float $consistency): static
    {
        $this->intervalConsistency = number_format($consistency, 2, '.', '');
        return $this;
    }

    public function getTransactionType(): TransactionType
    {
        return $this->transactionType;
    }

    public function setTransactionType(TransactionType $type): static
    {
        $this->transactionType = $type;
        return $this;
    }

    public function isDebit(): bool
    {
        return $this->transactionType === TransactionType::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->transactionType === TransactionType::CREDIT;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function calculateNextExpected(): ?DateTimeImmutable
    {
        if ($this->lastOccurrence === null) {
            return null;
        }

        $days = $this->frequency->getAverageDays();
        return $this->lastOccurrence->modify("+{$days} days");
    }

    public function updateNextExpected(): void
    {
        $this->nextExpected = $this->calculateNextExpected();
    }
}
