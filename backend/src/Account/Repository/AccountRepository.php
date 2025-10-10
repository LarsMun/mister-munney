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
     * 
     * @param Account $account Het account om op te slaan
     * @param bool $flush Of de wijzigingen direct naar de database geschreven moeten worden
     */
    public function save(Account $account, bool $flush = true): void
    {
        $this->em->persist($account);
        if ($flush) {
            $this->em->flush();
        }
    }

    /**
     * Haalt een account op via rekeningnummer.
     */
    public function findByAccountNumber(string $accountNumber): ?Account
    {
        return $this->findOneBy(['accountNumber' => $accountNumber]);
    }
    
    /**
     * Ververs een account entity vanuit de database.
     */
    public function refresh(Account $account): void
    {
        $this->em->refresh($account);
    }
}