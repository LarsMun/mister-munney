<?php

namespace App\Account\Service;

use App\Account\Repository\AccountRepository;
use App\Entity\Account;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AccountService
{
    private AccountRepository $accountRepository;
    private LoggerInterface $logger;

    public function __construct(
        AccountRepository $accountRepository,
        LoggerInterface $logger
    ) {
        $this->accountRepository = $accountRepository;
        $this->logger = $logger;
    }

    /**
     * Haalt alle accounts op.
     *
     * @return Account[] Lijst met accounts
     */
    public function getAll(): array
    {
        return $this->accountRepository->getAll();
    }

    /**
     * Haalt één account op op basis van ID.
     *
     * @param int $id Het ID van het account
     * @return Account Het account
     *
     * @throws NotFoundHttpException Als het account niet bestaat
     */
    public function getById(int $id): Account
    {
        $account = $this->accountRepository->getById($id);

        if (!$account) {
            throw new NotFoundHttpException('Account niet gevonden.');
        }

        return $account;
    }

    /**
     * Werkt de naam van een account bij op basis van ID.
     *
     * @param int $id Het ID van het account
     * @param array $data Gegevens die de nieuwe naam bevatten
     * @return Account Het bijgewerkte account
     *
     * @throws BadRequestHttpException Als 'name' ontbreekt
     * @throws NotFoundHttpException Als account niet bestaat
     * @throws ConflictHttpException Als de nieuwe naam al door een ander account wordt gebruikt
     */
    public function update(int $id, array $data): Account
    {
        if (empty($data['name'])) {
            throw new BadRequestHttpException('Naam is verplicht.');
        }

        $account = $this->getById($id);

        // Check of de nieuwe naam al bestaat bij een ander account
        $duplicate = $this->accountRepository->findOneBy([
            'name' => $data['name']
        ]);

        if ($duplicate && $duplicate->getId() !== $account->getId()) {
            throw new ConflictHttpException("Deze accountnaam is al in gebruik.");
        }

        $account->setName($data['name']);
        $this->accountRepository->save($account);

        return $account;
    }

    /**
     * Haalt een account op via rekeningnummer, of maakt een nieuwe aan als deze nog niet bestaat.
     *
     * @param string $accountNumber Het rekeningnummer
     * @return Account
     */
    public function getOrCreateAccountByNumber(string $accountNumber): Account
    {
        $account = $this->accountRepository->findByAccountNumber($accountNumber);

        if (!$account) {
            $account = new Account();
            $account->setAccountNumber($accountNumber);
            $this->accountRepository->save($account);

            $this->logger->info("Nieuw rekeningnummer aangemaakt: " . $accountNumber);
        }

        return $account;
    }
}