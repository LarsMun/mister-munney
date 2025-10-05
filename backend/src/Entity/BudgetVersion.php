<?php

namespace App\Entity;

use App\Budget\Repository\BudgetVersionRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity(repositoryClass: BudgetVersionRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_effective_from', columns: ['effective_from_month'])]
#[ORM\Index(name: 'idx_effective_until', columns: ['effective_until_month'])]
class BudgetVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Budget::class, inversedBy: 'budgetVersions')]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private ?Budget $budget;

    #[ORM\Column(name: "monthly_amount", type: Types::INTEGER)]
    private int $monthlyAmountInCents;

    #[ORM\Column(length: 7)]
    private string $effectiveFromMonth;

    #[ORM\Column(length: 7, nullable: true)]
    private ?string $effectiveUntilMonth = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $changeReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(?Budget $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getMonthlyAmount(): Money
    {
        return Money::EUR($this->monthlyAmountInCents);
    }

    public function setMonthlyAmount(Money $money): static
    {
        $this->monthlyAmountInCents = (int) $money->getAmount();
        return $this;
    }

    public function getEffectiveFromMonth(): string
    {
        return $this->effectiveFromMonth;
    }

    public function setEffectiveFromMonth(string $effectiveFromMonth): static
    {
        $this->effectiveFromMonth = $effectiveFromMonth;
        return $this;
    }

    public function getEffectiveUntilMonth(): ?string
    {
        return $this->effectiveUntilMonth;
    }

    public function setEffectiveUntilMonth(?string $effectiveUntilMonth): static
    {
        $this->effectiveUntilMonth = $effectiveUntilMonth;
        return $this;
    }

    public function getChangeReason(): ?string
    {
        return $this->changeReason;
    }

    public function setChangeReason(?string $changeReason): static
    {
        $this->changeReason = $changeReason;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }

    // --- Helper Methods ---

    /**
     * Check if this version is effective for a given month
     */
    public function isEffectiveForMonth(string $monthYear): bool
    {
        // Must be after or equal to effective from date
        if ($monthYear < $this->effectiveFromMonth) {
            return false;
        }

        // Must be before or equal to effective until date (if set)
        if ($this->effectiveUntilMonth !== null && $monthYear > $this->effectiveUntilMonth) {
            return false;
        }

        return true;
    }

    /**
     * Check if this version is currently active (no end date or end date in future)
     */
    public function isCurrent(): bool
    {
        $currentMonth = date('Y-m');
        return $this->isEffectiveForMonth($currentMonth);
    }

    /**
     * Get formatted display name
     */
    public function getDisplayName(): string
    {
        $money = $this->getMonthlyAmount();
        $amount = number_format($money->getAmount() / 100, 2, ',', '.');

        $until = $this->effectiveUntilMonth ? ' t/m ' . $this->effectiveUntilMonth : '';

        return sprintf(
            '€%s (vanaf %s%s)',
            $amount,
            $this->effectiveFromMonth,
            $until
        );
    }
}