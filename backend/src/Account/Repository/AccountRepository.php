<?php

namespace App\Account\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $em;

    public function __construct(ManagerRegistry $registry, EntityManagerInterface $em)
    {
        parent::__construct($registry, Account::class);
        $this->em = $em;
    }

    /**
     * Haalt een account op op basis van ID.
     */
    public function getById(int $id): ?Account
    {
        return $this->find($id);
    }

    /**
     * Haalt alle accounts op.
     *
     * @return Account[]
     */
    public function getAll(): array
    {
        return $this->findAll();
    }

    /**
     * Slaat een account op in de database.
     */
    public function save(Account $account): void
    {
        $this->em->persist($account);
        $this->em->flush();
    }

    /**
     * Haalt een account op via rekeningnummer.
     */
    public function findByAccountNumber(string $accountNumber): ?Account
    {
        return $this->findOneBy(['accountNumber' => $accountNumber]);
    }
}