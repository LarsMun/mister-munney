<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Transaction\Repository\TransactionRepository;
use DateTime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\Ignore;
use Money\Money;

#[ORM\UniqueConstraint(name: "unique_transaction", columns: ["hash"])]

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $hash = null;

    #[ORM\Column(type: 'date')]
    private DateTime $date;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Ignore]
    private ?Account $account = null;

    #[ORM\Column(length: 34, nullable: true)]
    private ?string $counterparty_account = null;

    #[ORM\Column(length: 10)]
    private ?string $transaction_code = null;

    #[ORM\Column(type: "string", enumType: TransactionType::class)]
    private ?TransactionType $transaction_type = null;

    #[ORM\Column(name: "amount", type: Types::INTEGER)]
    private ?int $amountInCents = null;

    #[ORM\Column(length: 100)]
    private ?string $mutation_type = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $notes = null;

    #[ORM\Column(name: "balance_after", type: Types::INTEGER)]
    private ?int $balanceAfterInCents = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $tag = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\ManyToOne(targetEntity: Transaction::class, inversedBy: 'splits')]
    #[ORM\JoinColumn(name: 'parent_transaction_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Transaction $parentTransaction = null;

    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'parentTransaction', cascade: ['persist', 'remove'])]
    private $splits;

    public function __construct()
    {
        $this->splits = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(string $hash): static
    {
        $this->hash = $hash;
        return $this;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(DateTime $date): static
    {
        $this->date = DateTime::createFromFormat('Y-m-d', $date->format('Y-m-d'));

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(Account $account): static
    {
        $this->account = $account;
        return $this;
    }

    public function getAccountId(): ?int
    {
        return $this->account?->getId();
    }

    public function getCounterpartyAccount(): ?string
    {
        return $this->counterparty_account;
    }

    public function setCounterpartyAccount(?string $counterparty_account): static
    {
        $this->counterparty_account = $counterparty_account;

        return $this;
    }

    public function getTransactionCode(): ?string
    {
        return $this->transaction_code;
    }

    public function setTransactionCode(string $transaction_code): static
    {
        $this->transaction_code = $transaction_code;

        return $this;
    }

    public function getTransactionType(): ?TransactionType
    {
        return $this->transaction_type;
    }

    public function setTransactionType(TransactionType $transaction_type): static
    {
        $this->transaction_type = $transaction_type;
        return $this;
    }

    public function isDebit(): bool
    {
        return $this->transaction_type === TransactionType::DEBIT;
    }

    public function isCredit(): bool
    {
        return $this->transaction_type === TransactionType::CREDIT;
    }

    public function getAmount(): ?Money
    {
        return $this->amountInCents !== null ? Money::EUR($this->amountInCents) : null;
    }

    public function setAmount(Money $money): static
    {
        $this->amountInCents = (int) $money->getAmount(); // getAmount() geeft centen
        return $this;
    }

    public function getMutationType(): ?string
    {
        return $this->mutation_type;
    }

    public function setMutationType(string $mutation_type): static
    {
        $this->mutation_type = $mutation_type;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    public function getBalanceAfter(): ?Money
    {
        return $this->balanceAfterInCents !== null ? Money::EUR($this->balanceAfterInCents) : null;
    }

    public function setBalanceAfter(Money $money): static
    {
        $this->balanceAfterInCents = (int) $money->getAmount();
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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getParentTransaction(): ?Transaction
    {
        return $this->parentTransaction;
    }

    public function setParentTransaction(?Transaction $parentTransaction): static
    {
        $this->parentTransaction = $parentTransaction;
        return $this;
    }

    public function getSplits()
    {
        // Lazy initialization for existing entities loaded from database
        if ($this->splits === null) {
            $this->splits = new \Doctrine\Common\Collections\ArrayCollection();
        }
        return $this->splits;
    }

    public function addSplit(Transaction $split): static
    {
        if (!$this->getSplits()->contains($split)) {
            $this->getSplits()->add($split);
            $split->setParentTransaction($this);
        }
        return $this;
    }

    public function removeSplit(Transaction $split): static
    {
        if ($this->getSplits()->removeElement($split)) {
            if ($split->getParentTransaction() === $this) {
                $split->setParentTransaction(null);
            }
        }
        return $this;
    }

    public function hasSplits(): bool
    {
        return $this->getSplits()->count() > 0;
    }

    public function isSplit(): bool
    {
        return $this->parentTransaction !== null;
    }
}
