<?php

namespace App\SavingsAccount\Repository;

use App\Entity\SavingsAccount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavingsAccount>
 */
class SavingsAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavingsAccount::class);
    }

    /**
     * Slaat een nieuwe of gewijzigde spaarrekening op in de database.
     *
     * @param SavingsAccount $entity De spaarrekening die moet worden opgeslagen.
     */
    public function save(SavingsAccount $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function findByNameAndAccountNumber(string $name, ?string $accountNumber): ?SavingsAccount
    {
        $qb = $this->createQueryBuilder('s')
            ->where('LOWER(s.name) = LOWER(:name)')
            ->setParameter('name', strtolower($name));

        if ($accountNumber !== null) {
            $qb->andWhere('LOWER(s.accountNumber) = LOWER(:accountNumber)')
                ->setParameter('accountNumber', strtolower($accountNumber));
        } else {
            $qb->andWhere('s.accountNumber IS NULL');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }
}