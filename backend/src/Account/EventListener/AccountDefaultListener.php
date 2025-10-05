<?php

namespace App\Account\EventListener;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

#[AsDoctrineListener(event: 'preFlush', priority: 0)]
readonly class AccountDefaultListener
{
    public function __construct(private EntityManagerInterface $em) {}

    public function preFlush(PreFlushEventArgs $event): void
    {
        $uow = $this->em->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Account) {
                continue;
            }

            if ($entity->isDefault()) {
                $repo = $this->em->getRepository(Account::class);

                $others = $repo->createQueryBuilder('a')
                    ->where('a.isDefault = true')
                    ->andWhere('a.id != :id')
                    ->setParameter('id', $entity->getId())
                    ->getQuery()
                    ->getResult();

                foreach ($others as $other) {
                    $other->setIsDefault(false);
                    $this->em->persist($other);

                    // 🔥 forceer Doctrine om dit alsnog mee te nemen
                    $uow->recomputeSingleEntityChangeSet(
                        $this->em->getClassMetadata(Account::class),
                        $other
                    );
                }
            }
        }
    }
}