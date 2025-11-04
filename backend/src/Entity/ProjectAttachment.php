<?php

namespace App\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class ProjectAttachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Budget::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Budget $budget;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500)]
    private string $fileUrl;

    #[ORM\Column(length: 255)]
    private string $originalFilename;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = null; // 'offer', 'contract', 'invoice', 'document', 'other'

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $uploadedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBudget(): Budget
    {
        return $this->budget;
    }

    public function setBudget(Budget $budget): static
    {
        $this->budget = $budget;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getFileUrl(): string
    {
        return $this->fileUrl;
    }

    public function setFileUrl(string $fileUrl): static
    {
        $this->fileUrl = $fileUrl;
        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getUploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PrePersist]
    public function setUploadedAtValue(): void
    {
        $this->uploadedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
