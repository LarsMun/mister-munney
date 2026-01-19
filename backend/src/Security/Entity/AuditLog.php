<?php

namespace App\Security\Entity;

use App\Security\Repository\AuditLogRepository;
use App\User\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Tracks security-relevant actions for audit purposes
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_type', 'entity_id'])]
class AuditLog
{
    public const ACTION_LOGIN = 'login';
    public const ACTION_LOGOUT = 'logout';
    public const ACTION_LOGIN_FAILED = 'login_failed';
    public const ACTION_ACCOUNT_LOCKED = 'account_locked';
    public const ACTION_ACCOUNT_UNLOCKED = 'account_unlocked';
    public const ACTION_PASSWORD_CHANGED = 'password_changed';
    public const ACTION_ACCOUNT_SHARED = 'account_shared';
    public const ACTION_ACCOUNT_SHARE_REVOKED = 'account_share_revoked';
    public const ACTION_ACCOUNT_SHARE_ACCEPTED = 'account_share_accepted';
    public const ACTION_TRANSACTION_IMPORTED = 'transaction_imported';
    public const ACTION_BULK_CATEGORIZE = 'bulk_categorize';
    public const ACTION_PATTERN_CREATED = 'pattern_created';
    public const ACTION_PATTERN_DELETED = 'pattern_deleted';
    public const ACTION_BUDGET_CREATED = 'budget_created';
    public const ACTION_BUDGET_DELETED = 'budget_deleted';
    public const ACTION_CATEGORY_CREATED = 'category_created';
    public const ACTION_CATEGORY_DELETED = 'category_deleted';
    public const ACTION_DATA_EXPORT = 'data_export';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $action;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    public function setEntityId(?int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
