<?php

namespace App\Shared\Controller;

use App\Account\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait for verifying account ownership in controllers.
 *
 * Controllers using this trait must:
 * - Extend AbstractController (for getUser() and json() methods)
 * - Have an AccountRepository injected as $this->accountRepository
 */
trait AccountOwnershipTrait
{
    protected abstract function getAccountRepository(): AccountRepository;

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

        $account = $this->getAccountRepository()->find($accountId);
        if (!$account) {
            return $this->json(['error' => 'Account not found'], Response::HTTP_NOT_FOUND);
        }

        if (!$account->isOwnedBy($user)) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return null;
    }
}
