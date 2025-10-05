<?php

namespace App\SavingsAccount\Service;

use App\Account\Repository\AccountRepository;
use App\Entity\SavingsAccount;
use App\Pattern\Repository\PatternRepository;
use App\SavingsAccount\DTO\CreateSavingsAccountDTO;
use App\SavingsAccount\DTO\UpdateSavingsAccountDTO;
use App\SavingsAccount\Mapper\SavingsAccountMapper;
use App\SavingsAccount\Repository\SavingsAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SavingsAccountService
{
    private EntityManagerInterface $entityManager;
    private SavingsAccountRepository $savingsAccountRepository;
    private AccountRepository $accountRepository;
    private SavingsAccountMapper $savingsAccountMapper;
    private PatternRepository $patternRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        SavingsAccountRepository $savingsAccountRepository,
        AccountRepository $accountRepository,
        SavingsAccountMapper $savingsAccountMapper,
        PatternRepository $patternRepository,
    )
    {
        $this->entityManager = $entityManager;
        $this->savingsAccountRepository = $savingsAccountRepository;
        $this->accountRepository = $accountRepository;
        $this->savingsAccountMapper = $savingsAccountMapper;
        $this->patternRepository = $patternRepository;
    }

    /**
     * Haalt alle spaarrekeningen op die gekoppeld zijn aan een specifiek account.
     *
     * @return SavingsAccount[] Een array van alle spaarrekeningen binnen het opgegeven account.
     */
    public function getAllSavingsAccounts(int $accountId): array
    {
        return $this->savingsAccountRepository->findBy(['account' => $accountId]);
    }

    /**
     * Haalt een specifieke spaarrekening op basis van het ID.
     *
     * @param int $id Het ID van de spaarrekening.
     *
     * @return SavingsAccount|null De spaarrekening als gevonden, anders null.
     */
    public function getSavingsAccountById(int $id): ?SavingsAccount
    {
        return $this->savingsAccountRepository->find($id);
    }

    /**
     * Verwerkt de aanmaak van een nieuwe spaarrekening op basis van een DTO.
     *
     * @param CreateSavingsAccountDTO $dto De DTO met de vereiste invoerwaarden.
     *
     * @return SavingsAccount De nieuw aangemaakte en opgeslagen spaarrekening.
     *
     * @throws InvalidArgumentException Als het account niet bestaat of een spaarrekening met dezelfde naam al bestaat voor dit account.
     */
    public function createFromDto(CreateSavingsAccountDTO $dto, int $accountId): SavingsAccount
    {
        $account = $this->accountRepository->getById($accountId);
        if (!$account) {
            throw new NotFoundHttpException("Account niet gevonden");
        }

        $existing = $this->savingsAccountRepository->findOneBy([
            'name' => $dto->name,
            'account' => $account
        ]);

        if ($existing) {
            throw new ConflictHttpException("Spaarrekening met deze naam bestaat al voor dit account");
        }

        $entity = $this->savingsAccountMapper->fromCreateDto($dto, $account);
        $this->savingsAccountRepository->save($entity);

        return $entity;
    }

    /**
     * Wijzigt een bestaande spaarrekening.
     *
     * @param int $id
     * @param int $accountId Het ID van de nieuwe of huidige gekoppelde betaalrekening.
     * @param UpdateSavingsAccountDTO $dto
     * @return SavingsAccount De bijgewerkte spaarrekening.
     *
     */
    public function updateFromDto(int $id, int $accountId, UpdateSavingsAccountDTO $dto): SavingsAccount
    {
        $savingsAccount = $this->savingsAccountRepository->find($id);
        if (!$savingsAccount) {
            throw new NotFoundHttpException('Spaarrekening niet gevonden.');
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            throw new NotFoundHttpException('Gekoppelde betaalrekening niet gevonden.');
        }

        $newName = $dto->name ?? $savingsAccount->getName();
        $newTargetAmount = $dto->targetAmount ?? $savingsAccount->getTargetAmount();
        $newColor = $dto->color ?? $savingsAccount->getColor();

        if ($savingsAccount->getName() !== $newName || $savingsAccount->getAccount()->getId() !== $account->getId()) {
            $existingAccount = $this->savingsAccountRepository->findOneBy(['name' => $newName, 'account' => $account]);
            if ($existingAccount && $existingAccount->getId() !== $id) {
                throw new ConflictHttpException('Een spaarrekening met deze naam bestaat al voor dit account.');
            }
        }

        $savingsAccount->setName($newName);
        $savingsAccount->setAccount($account);
        $savingsAccount->setTargetAmount($newTargetAmount);
        $savingsAccount->setColor($newColor); // ← ✅ toegevoegd

        $this->entityManager->flush();

        return $savingsAccount;
    }

    /**
     * Verwijdert een spaarrekening uit de database.
     * Verwijdert ook patronen die deze spaarrekening gebruiken en geen categorie hebben.
     *
     * @param SavingsAccount $savingsAccount De spaarrekening die verwijderd moet worden.
     *
     * @return void
     */
    public function deleteSavingsAccount(SavingsAccount $savingsAccount): void
    {
        // Vind alle patronen die deze spaarrekening gebruiken
        $patterns = $this->patternRepository->findBy(['savingsAccount' => $savingsAccount]);

        // Verwijder patronen die geen categorie hebben
        foreach ($patterns as $pattern) {
            if ($pattern->getCategory() === null) {
                $this->patternRepository->remove($pattern, false);
            }
        }

        $this->entityManager->remove($savingsAccount);
        $this->entityManager->flush();
    }
}