<?php

namespace App\Entity;

use App\Budget\Repository\BudgetRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Budget
{
    public function __construct()
    {
        $this->budgetVersions = new ArrayCollection();
        $this->categories = new ArrayCollection();
    }

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: "CASCADE")]
    private Account $account;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(targetEntity: BudgetVersion::class, mappedBy: 'budget', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['effectiveFromMonth' => 'DESC'])]
    private Collection $budgetVersions;

    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'budget')]
    private Collection $categories;

    // --- Getters & Setters ---

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getCreatedAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
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

    /**
     * @return Collection<int, BudgetVersion>
     */
    public function getBudgetVersions(): Collection
    {
        return $this->budgetVersions;
    }

    public function addBudgetVersion(BudgetVersion $budgetVersion): static
    {
        if (!$this->budgetVersions->contains($budgetVersion)) {
            $this->budgetVersions->add($budgetVersion);
            $budgetVersion->setBudget($this);
        }
        return $this;
    }

    public function removeBudgetVersion(BudgetVersion $budgetVersion): static
    {
        if ($this->budgetVersions->removeElement($budgetVersion)) {
            if ($budgetVersion->getBudget() === $this) {
                $budgetVersion->setBudget(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
            $category->setBudget($this);
        }
        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            if ($category->getBudget() === $this) {
                $category->setBudget(null);
            }
        }
        return $this;
    }

    /**
     * Get effective version for a specific month
     */
    public function getEffectiveVersion(string $monthYear): ?BudgetVersion
    {
        foreach ($this->budgetVersions as $version) {
            if ($version->isEffectiveForMonth($monthYear)) {
                return $version;
            }
        }
        return null;
    }

    /**
     * Get current/latest version
     */
    public function getCurrentVersion(): ?BudgetVersion
    {
        if ($this->budgetVersions->isEmpty()) {
            return null;
        }
        return $this->budgetVersions->first();
    }
}