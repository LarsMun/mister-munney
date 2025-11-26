<?php

namespace App\Entity;

use App\Entity\Account;
use App\Enum\TransactionType;
use App\Enum\MatchType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity]
class Pattern
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $minAmountInCents = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $maxAmountInCents = null;

    #[ORM\Column(type: 'string', nullable: true, enumType: TransactionType::class)]
    private ?TransactionType $transactionType = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', enumType: MatchType::class)]
    private MatchType $matchTypeDescription = MatchType::LIKE;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', enumType: MatchType::class)]
    private MatchType $matchTypeNotes = MatchType::LIKE;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tag = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $strict = false;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $uniqueHash;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    // --- Getters & Setters ---

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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getMinAmount(): ?Money
    {
        return $this->minAmountInCents !== null ? Money::EUR($this->minAmountInCents) : null;
    }

    public function setMinAmount(?Money $money): static
    {
        $this->minAmountInCents = $money?->getAmount() !== null ? (int) $money->getAmount() : null;
        return $this;
    }

    public function getMaxAmount(): ?Money
    {
        return $this->maxAmountInCents !== null ? Money::EUR($this->maxAmountInCents) : null;
    }

    public function setMaxAmount(?Money $money): static
    {
        $this->maxAmountInCents = $money?->getAmount() !== null ? (int) $money->getAmount() : null;
        return $this;
    }

    public function getTransactionType(): ?TransactionType
    {
        return $this->transactionType;
    }

    public function setTransactionType(?TransactionType $transactionType): static
    {
        $this->transactionType = $transactionType;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMatchTypeDescription(): MatchType
    {
        return $this->matchTypeDescription;
    }

    public function setMatchTypeDescription(MatchType $matchTypeDescription): static
    {
        $this->matchTypeDescription = $matchTypeDescription;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getMatchTypeNotes(): MatchType
    {
        return $this->matchTypeNotes;
    }

    public function setMatchTypeNotes(MatchType $matchTypeNotes): static
    {
        $this->matchTypeNotes = $matchTypeNotes;
        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function setStrict(bool $strict): static
    {
        $this->strict = $strict;
        return $this;
    }

    public function getUniqueHash(): string
    {
        return $this->uniqueHash;
    }

    public function setUniqueHash(string $uniqueHash): void
    {
        $this->uniqueHash = $uniqueHash;
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

}