<?php

namespace App\Entity;

use App\Forecast\Repository\ForecastItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

/**
 * Een forecast item koppelt een budget of categorie aan de cashflow forecast
 * met een verwacht maandelijks bedrag.
 */
#[ORM\Entity(repositoryClass: ForecastItemRepository::class)]
#[ORM\Table(name: 'forecast_item')]
#[ORM\HasLifecycleCallbacks]
class ForecastItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    /**
     * Gekoppeld budget (optioneel - OF budget OF categorie)
     */
    #[ORM\ManyToOne(targetEntity: Budget::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Budget $budget = null;

    /**
     * Gekoppelde categorie (optioneel - OF budget OF categorie)
     */
    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Category $category = null;

    /**
     * Type: INCOME of EXPENSE
     */
    #[ORM\Column(length: 20)]
    private string $type = 'EXPENSE';

    /**
     * Verwacht maandelijks bedrag in centen
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $expectedAmountInCents = 0;

    /**
     * Positie in de lijst (voor drag & drop volgorde)
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position = 0;

    /**
     * Custom naam (optioneel, anders wordt budget/categorie naam gebruikt)
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $customName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

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

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(?Budget $budget): static
    {
        $this->budget = $budget;
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isIncome(): bool
    {
        return $this->type === 'INCOME';
    }

    public function isExpense(): bool
    {
        return $this->type === 'EXPENSE';
    }

    public function getExpectedAmountInCents(): int
    {
        return $this->expectedAmountInCents;
    }

    public function setExpectedAmountInCents(int $amountInCents): static
    {
        $this->expectedAmountInCents = $amountInCents;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getCustomName(): ?string
    {
        return $this->customName;
    }

    public function setCustomName(?string $customName): static
    {
        $this->customName = $customName;
        return $this;
    }

    /**
     * Geeft de naam terug: custom naam, of budget/categorie naam
     */
    public function getName(): string
    {
        if ($this->customName) {
            return $this->customName;
        }

        if ($this->budget) {
            return $this->budget->getName();
        }

        if ($this->category) {
            return $this->category->getName();
        }

        return 'Onbekend';
    }

    /**
     * Geeft het icoon terug van budget of categorie
     */
    public function getIcon(): ?string
    {
        if ($this->budget) {
            return $this->budget->getIcon();
        }

        if ($this->category) {
            return $this->category->getIcon();
        }

        return null;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
