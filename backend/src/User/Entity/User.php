<?php

namespace App\User\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: 'App\User\Repository\UserRepository')]
#[ORM\Table(name: 'user')]
#[ORM\UniqueConstraint(name: 'UNIQ_USER_EMAIL', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isLocked = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lockedAt = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $unlockToken = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $unlockTokenExpiresAt = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: 'App\Entity\AccountUser')]
    private Collection $accountUsers;

    public function __construct()
    {
        $this->accountUsers = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isLocked(): bool
    {
        return $this->isLocked;
    }

    public function setIsLocked(bool $isLocked): self
    {
        $this->isLocked = $isLocked;
        return $this;
    }

    public function getLockedAt(): ?\DateTime
    {
        return $this->lockedAt;
    }

    public function setLockedAt(?\DateTime $lockedAt): self
    {
        $this->lockedAt = $lockedAt;
        return $this;
    }

    public function getUnlockToken(): ?string
    {
        return $this->unlockToken;
    }

    public function setUnlockToken(?string $unlockToken): self
    {
        $this->unlockToken = $unlockToken;
        return $this;
    }

    public function getUnlockTokenExpiresAt(): ?\DateTime
    {
        return $this->unlockTokenExpiresAt;
    }

    public function setUnlockTokenExpiresAt(?\DateTime $unlockTokenExpiresAt): self
    {
        $this->unlockTokenExpiresAt = $unlockTokenExpiresAt;
        return $this;
    }

    /**
     * @return Collection<int, AccountUser>
     */
    public function getAccountUsers(): Collection
    {
        return $this->accountUsers;
    }

    /**
     * Get all accounts this user has active access to
     *
     * @return array<Account>
     */
    public function getAccounts(): array
    {
        return $this->accountUsers
            ->filter(function($accountUser) {
                return $accountUser->isActive();
            })
            ->map(function($accountUser) {
                return $accountUser->getAccount();
            })
            ->toArray();
    }

    /**
     * @deprecated Use Account::addOwner() or AccountSharingService instead
     */
    public function addAccount($account): self
    {
        // Backwards compatibility - no-op, relationship managed by Account entity
        return $this;
    }

    /**
     * @deprecated Use AccountSharingService::revokeAccess() instead
     */
    public function removeAccount($account): self
    {
        // Backwards compatibility - no-op, relationship managed by Account entity
        return $this;
    }

    /**
     * Check if user owns (is owner of) the given account
     */
    public function ownsAccount($account): bool
    {
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->getAccount() === $account &&
                $accountUser->isOwner() &&
                $accountUser->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any access to the given account
     */
    public function hasAccessToAccount($account): bool
    {
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->getAccount() === $account && $accountUser->isActive()) {
                return true;
            }
        }

        return false;
    }
}
