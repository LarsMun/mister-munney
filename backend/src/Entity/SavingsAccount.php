<?php

namespace App\Entity;

use App\SavingsAccount\Repository\SavingsAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: SavingsAccountRepository::class)]
#[ORM\UniqueConstraint(name: "unique_savings_account", columns: ["name", "account_id"])]

class SavingsAccount
{
    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->patterns = new ArrayCollection();
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

    #[ORM\Column(type: "decimal", precision: 10, scale: 2, nullable: true)]
    private ?string $targetAmount = null;

    #[ORM\Column(length: 7, options: ["default" => "#CCCCCC"])]
    private ?string $color = '#CCCCCC';

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'savingsAccount')]
    private Collection $transactions;

    #[ORM\OneToMany(targetEntity: Pattern::class, mappedBy: 'savingsAccount')]
    private Collection $patterns;

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

    public function getTargetAmount(): ?string
    {
        return $this->targetAmount;
    }

    public function setTargetAmount(?string $targetAmount): static
    {
        $this->targetAmount = $targetAmount;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;
        return $this;
    }

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setSavingsAccount($this);
        }
        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            if ($transaction->getSavingsAccount() === $this) {
                $transaction->setSavingsAccount(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Pattern>
     */
    public function getPatterns(): Collection
    {
        return $this->patterns;
    }

    public function addPattern(Pattern $pattern): static
    {
        if (!$this->patterns->contains($pattern)) {
            $this->patterns->add($pattern);
            $pattern->setSavingsAccount($this);
        }
        return $this;
    }

    public function removePattern(Pattern $pattern): static
    {
        if ($this->patterns->removeElement($pattern)) {
            if ($pattern->getSavingsAccount() === $this) {
                $pattern->setSavingsAccount(null);
            }
        }
        return $this;
    }
}