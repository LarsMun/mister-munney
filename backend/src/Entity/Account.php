<?php

namespace App\Entity;

use App\Account\Repository\AccountRepository;
use App\Enum\AccountUserRole;
use App\Enum\AccountUserStatus;
use App\User\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 34, unique: true)]
    private string $accountNumber;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\OneToMany(mappedBy: 'account', targetEntity: AccountUser::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $accountUsers;

    public function __construct()
    {
        $this->accountUsers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountNumber(): ?string
    {
        return $this->accountNumber;
    }

    public function setAccountNumber(string $accountNumber): static
    {
        $this->accountNumber = $accountNumber;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

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
     * Get all users with access to this account (active only)
     *
     * @return User[]
     */
    public function getUsers(): array
    {
        return $this->accountUsers
            ->filter(fn(AccountUser $au) => $au->isActive())
            ->map(fn(AccountUser $au) => $au->getUser())
            ->toArray();
    }

    /**
     * Check if user is the owner of this account
     */
    public function isOwnedBy(User $user): bool
    {
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->getUser() === $user &&
                $accountUser->isOwner() &&
                $accountUser->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has any access to this account (active only)
     */
    public function hasAccess(User $user): bool
    {
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->getUser() === $user && $accountUser->isActive()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the owner of this account
     */
    public function getOwner(): ?User
    {
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->isOwner() && $accountUser->isActive()) {
                return $accountUser->getUser();
            }
        }

        return null;
    }

    /**
     * Get all shared users (non-owners with active access)
     *
     * @return User[]
     */
    public function getSharedUsers(): array
    {
        $sharedUsers = [];

        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->isShared() && $accountUser->isActive()) {
                $sharedUsers[] = $accountUser->getUser();
            }
        }

        return $sharedUsers;
    }

    /**
     * Add user as owner (used when creating new account)
     */
    public function addOwner(User $user): self
    {
        // Check if user already has access
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->getUser() === $user) {
                return $this;
            }
        }

        $accountUser = new AccountUser();
        $accountUser->setAccount($this);
        $accountUser->setUser($user);
        $accountUser->setRole(AccountUserRole::OWNER);
        $accountUser->setStatus(AccountUserStatus::ACTIVE);

        $this->accountUsers->add($accountUser);

        return $this;
    }

    /**
     * @deprecated Use AccountSharingService instead for proper access control
     */
    public function addUser(User $user): self
    {
        // For backwards compatibility - adds as owner
        return $this->addOwner($user);
    }

    /**
     * @deprecated Use AccountSharingService::revokeAccess() instead
     */
    public function removeUser(User $user): self
    {
        foreach ($this->accountUsers as $accountUser) {
            if ($accountUser->getUser() === $user) {
                $this->accountUsers->removeElement($accountUser);
                break;
            }
        }

        return $this;
    }
}
