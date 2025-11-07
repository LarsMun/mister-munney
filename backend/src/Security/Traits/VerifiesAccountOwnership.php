<?php

namespace App\Security\Traits;

use App\Account\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for verifying account ownership in controllers.
 *
 * Usage: Add this trait to any controller that needs account ownership verification.
 * Requires: AccountRepository must be injected in the constructor.
 */
trait VerifiesAccountOwnership
{
    private AccountRepository $accountRepository;

    /**
     * Verify that the authenticated user owns the specified account.
     *
     * @param int $accountId The account ID to verify
     * @return JsonResponse|null Returns error response if verification fails, null if successful
     */
    protected function verifyAccountOwnership(int $accountId): ?JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $account = $this->accountRepository->find($accountId);
        if (!$account) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null; // No error, ownership verified
    }
}
