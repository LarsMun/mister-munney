<?php

namespace App\Entity;

use App\Enum\AccountUserRole;
use App\Enum\AccountUserStatus;
use App\User\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Join entity for Account-User relationship with access control
 *
 * Replaces simple many-to-many with role-based access and invitation workflow.
 * Prevents unauthorized account access via CSV import.
 */
#[ORM\Entity(repositoryClass: 'App\Account\Repository\AccountUserRepository')]
#[ORM\Table(name: 'user_account')]
#[ORM\UniqueConstraint(name: 'unique_user_account', columns: ['user_id', 'account_id'])]
#[ORM\HasLifecycleCallbacks]
class AccountUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'accountUsers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Account::class, inversedBy: 'accountUsers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Account $account;

    #[ORM\Column(type: 'string', enumType: AccountUserRole::class)]
    private AccountUserRole $role;

    #[ORM\Column(type: 'string', enumType: AccountUserStatus::class)]
    private AccountUserStatus $status;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $invitedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
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

    public function getRole(): AccountUserRole
    {
        return $this->role;
    }

    public function setRole(AccountUserRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getStatus(): AccountUserStatus
    {
        return $this->status;
    }

    public function setStatus(AccountUserStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function setInvitedBy(?User $invitedBy): static
    {
        $this->invitedBy = $invitedBy;
        return $this;
    }

    public function getInvitedAt(): ?DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function setInvitedAt(?DateTimeImmutable $invitedAt): static
    {
        $this->invitedAt = $invitedAt;
        return $this;
    }

    public function getAcceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;
        return $this;
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;
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

    // --- Helper Methods ---

    /**
     * Check if this user has owner role
     */
    public function isOwner(): bool
    {
        return $this->role === AccountUserRole::OWNER;
    }

    /**
     * Check if this user has shared role
     */
    public function isShared(): bool
    {
        return $this->role === AccountUserRole::SHARED;
    }

    /**
     * Check if access is active
     */
    public function isActive(): bool
    {
        return $this->status === AccountUserStatus::ACTIVE;
    }

    /**
     * Check if invitation is pending
     */
    public function isPending(): bool
    {
        return $this->status === AccountUserStatus::PENDING;
    }

    /**
     * Check if access is revoked
     */
    public function isRevoked(): bool
    {
        return $this->status === AccountUserStatus::REVOKED;
    }

    /**
     * Check if invitation has expired
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    /**
     * Check if user can accept this invitation
     */
    public function canBeAccepted(): bool
    {
        return $this->isPending() && !$this->isExpired();
    }

    // --- Lifecycle Callbacks ---

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
