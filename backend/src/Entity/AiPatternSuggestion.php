<?php

namespace App\Entity;

use App\Enum\AiPatternSuggestionStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Pattern\Repository\AiPatternSuggestionRepository::class)]
#[ORM\Table(name: 'ai_pattern_suggestion')]
#[ORM\Index(columns: ['account_id', 'status'])]
#[ORM\Index(columns: ['pattern_hash'])]
class AiPatternSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $descriptionPattern = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $notesPattern = null;

    #[ORM\Column(length: 100)]
    private string $suggestedCategoryName;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Category $existingCategory = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $matchCount;

    #[ORM\Column(type: Types::FLOAT)]
    private float $confidence;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reasoning = null;

    #[ORM\Column(type: Types::JSON)]
    private array $exampleTransactions = [];

    #[ORM\Column(type: 'string', enumType: AiPatternSuggestionStatus::class)]
    private AiPatternSuggestionStatus $status = AiPatternSuggestionStatus::PENDING;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $patternHash;

    #[ORM\ManyToOne(targetEntity: Pattern::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Pattern $createdPattern = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $acceptedDescriptionPattern = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $acceptedNotesPattern = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $acceptedCategoryName = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

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

    public function getDescriptionPattern(): ?string
    {
        return $this->descriptionPattern;
    }

    public function setDescriptionPattern(?string $descriptionPattern): static
    {
        $this->descriptionPattern = $descriptionPattern;
        return $this;
    }

    public function getNotesPattern(): ?string
    {
        return $this->notesPattern;
    }

    public function setNotesPattern(?string $notesPattern): static
    {
        $this->notesPattern = $notesPattern;
        return $this;
    }

    public function getSuggestedCategoryName(): string
    {
        return $this->suggestedCategoryName;
    }

    public function setSuggestedCategoryName(string $suggestedCategoryName): static
    {
        $this->suggestedCategoryName = $suggestedCategoryName;
        return $this;
    }

    public function getExistingCategory(): ?Category
    {
        return $this->existingCategory;
    }

    public function setExistingCategory(?Category $existingCategory): static
    {
        $this->existingCategory = $existingCategory;
        return $this;
    }

    public function getMatchCount(): int
    {
        return $this->matchCount;
    }

    public function setMatchCount(int $matchCount): static
    {
        $this->matchCount = $matchCount;
        return $this;
    }

    public function getConfidence(): float
    {
        return $this->confidence;
    }

    public function setConfidence(float $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function getReasoning(): ?string
    {
        return $this->reasoning;
    }

    public function setReasoning(?string $reasoning): static
    {
        $this->reasoning = $reasoning;
        return $this;
    }

    public function getExampleTransactions(): array
    {
        return $this->exampleTransactions;
    }

    public function setExampleTransactions(array $exampleTransactions): static
    {
        $this->exampleTransactions = $exampleTransactions;
        return $this;
    }

    public function getStatus(): AiPatternSuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(AiPatternSuggestionStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getPatternHash(): string
    {
        return $this->patternHash;
    }

    public function setPatternHash(string $patternHash): static
    {
        $this->patternHash = $patternHash;
        return $this;
    }

    public function getCreatedPattern(): ?Pattern
    {
        return $this->createdPattern;
    }

    public function setCreatedPattern(?Pattern $createdPattern): static
    {
        $this->createdPattern = $createdPattern;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;
        return $this;
    }

    public function getAcceptedDescriptionPattern(): ?string
    {
        return $this->acceptedDescriptionPattern;
    }

    public function setAcceptedDescriptionPattern(?string $acceptedDescriptionPattern): static
    {
        $this->acceptedDescriptionPattern = $acceptedDescriptionPattern;
        return $this;
    }

    public function getAcceptedNotesPattern(): ?string
    {
        return $this->acceptedNotesPattern;
    }

    public function setAcceptedNotesPattern(?string $acceptedNotesPattern): static
    {
        $this->acceptedNotesPattern = $acceptedNotesPattern;
        return $this;
    }

    public function getAcceptedCategoryName(): ?string
    {
        return $this->acceptedCategoryName;
    }

    public function setAcceptedCategoryName(?string $acceptedCategoryName): static
    {
        $this->acceptedCategoryName = $acceptedCategoryName;
        return $this;
    }

    /**
     * Generate a unique hash for this pattern suggestion
     * Based on account, description pattern, and notes pattern
     */
    public function generatePatternHash(): string
    {
        $data = implode('|', [
            $this->account->getId(),
            $this->descriptionPattern ?? '',
            $this->notesPattern ?? ''
        ]);

        return hash('sha256', $data);
    }
}
