<?php

namespace App\Budget\Repository;

use App\Entity\BudgetVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

class BudgetVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetVersion::class);
    }

    /**
     * @throws Exception
     */
    public function save(BudgetVersion $budgetVersion): BudgetVersion
    {
        $this->getEntityManager()->beginTransaction();
        try {
            $this->getEntityManager()->persist($budgetVersion);
            $this->getEntityManager()->flush();
            $this->getEntityManager()->commit();
            return $budgetVersion;
        } catch (Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }
    }

    public function delete(BudgetVersion $budgetVersion): void
    {
        $this->getEntityManager()->beginTransaction();
        try {
            $this->getEntityManager()->remove($budgetVersion);
            $this->getEntityManager()->flush();
            $this->getEntityManager()->commit();
        } catch (\Exception $e) {
            $this->getEntityManager()->rollback();
            throw $e;
        }
    }
}