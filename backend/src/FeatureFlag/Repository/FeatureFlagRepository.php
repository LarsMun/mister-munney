<?php

namespace App\FeatureFlag\Repository;

use App\Entity\FeatureFlag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FeatureFlagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeatureFlag::class);
    }

    public function findByName(string $name): ?FeatureFlag
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findAllEnabled(): array
    {
        return $this->findBy(['enabled' => true]);
    }

    public function save(FeatureFlag $featureFlag): FeatureFlag
    {
        $this->getEntityManager()->persist($featureFlag);
        $this->getEntityManager()->flush();

        return $featureFlag;
    }
}
