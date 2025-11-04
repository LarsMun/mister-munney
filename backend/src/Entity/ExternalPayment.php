<?php

namespace App\Entity;

use App\Enum\PayerSource;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Money\Money;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class ExternalPayment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Budget::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Budget $budget;

    #[ORM\Column(name: "amount", type: Types::INTEGER)]
    private int $amountInCents;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $paidOn;

    #[ORM\Column(type: "string", enumType: PayerSource::class)]
    private PayerSource $payerSource;

    #[ORM\Column(type: Types::TEXT)]
    private string $note;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $attachmentUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

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

    public function getAmount(): Money
    {
        return Money::EUR($this->amountInCents);
    }

    public function setAmount(Money $money): static
    {
        $this->amountInCents = (int) $money->getAmount();
        return $this;
    }

    public function getPaidOn(): DateTimeImmutable
    {
        return $this->paidOn;
    }

    public function setPaidOn(DateTimeImmutable $paidOn): static
    {
        $this->paidOn = $paidOn;
        return $this;
    }

    public function getPayerSource(): PayerSource
    {
        return $this->payerSource;
    }

    public function setPayerSource(PayerSource $payerSource): static
    {
        $this->payerSource = $payerSource;
        return $this;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getAttachmentUrl(): ?string
    {
        return $this->attachmentUrl;
    }

    public function setAttachmentUrl(?string $attachmentUrl): static
    {
        $this->attachmentUrl = $attachmentUrl;
        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
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
}
