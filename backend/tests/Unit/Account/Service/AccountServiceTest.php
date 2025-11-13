<?php

namespace App\Tests\Unit\Account\Service;

use App\Account\Exception\AccountAccessDeniedException;
use App\Account\Service\AccountService;
use App\Entity\Account;
use App\Entity\AccountUser;
use App\Enum\AccountUserRole;
use App\Enum\AccountUserStatus;
use App\Tests\TestCase\DatabaseTestCase;
use App\User\Entity\User;

/**
 * Unit tests for AccountService - focuses on security fix for account ownership
 */
class AccountServiceTest extends DatabaseTestCase
{
    private AccountService $accountService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountService = $this->container->get(AccountService::class);
    }

    public function testCreateNewAccountAssignsOwner(): void
    {
        // Create user
        $user = $this->createUser('user@example.com');

        // Import should create account and assign user as owner
        $account = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0001', $user);

        $this->assertNotNull($account->getId());
        $this->assertEquals('NL91TEST0001', $account->getAccountNumber());
        $this->assertTrue($account->isOwnedBy($user));
        $this->assertTrue($account->hasAccess($user));
        $this->assertEquals($user->getId(), $account->getOwner()->getId());
    }

    public function testCannotClaimExistingAccountOwnedByOtherUser(): void
    {
        // User A creates account
        $userA = $this->createUser('usera@example.com');
        $account = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0002', $userA);

        // User B tries to import CSV with same account number
        $userB = $this->createUser('userb@example.com');

        $this->expectException(AccountAccessDeniedException::class);
        $this->expectExceptionMessage('Access denied to account NL91****0002');

        $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0002', $userB);
    }

    public function testOwnerCanAccessOwnAccount(): void
    {
        // User creates account
        $user = $this->createUser('user@example.com');
        $account = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0003', $user);

        // User can access again (subsequent CSV imports)
        $accountAgain = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0003', $user);

        $this->assertEquals($account->getId(), $accountAgain->getId());
    }

    public function testSharedUserCanAccessAccount(): void
    {
        // User A creates account
        $userA = $this->createUser('usera@example.com');
        $account = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0004', $userA);

        // Manually add User B as shared user (simulating invitation acceptance)
        $userB = $this->createUser('userb@example.com');
        $this->addSharedUser($account, $userB);

        // User B should be able to import transactions for this account
        $accountForB = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0004', $userB);

        $this->assertEquals($account->getId(), $accountForB->getId());
        $this->assertTrue($account->hasAccess($userB));
        $this->assertFalse($account->isOwnedBy($userB)); // Not owner, just shared
    }

    public function testRevokedUserCannotAccessAccount(): void
    {
        // User A creates account and shares with User B
        $userA = $this->createUser('usera@example.com');
        $account = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0005', $userA);

        $userB = $this->createUser('userb@example.com');
        $accountUser = $this->addSharedUser($account, $userB);

        // User A revokes User B's access
        $accountUser->setStatus(AccountUserStatus::REVOKED);
        $this->entityManager->flush();

        // User B should not be able to access
        $this->expectException(AccountAccessDeniedException::class);
        $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0005', $userB);
    }

    public function testPendingInvitationDoesNotGrantAccess(): void
    {
        // User A creates account
        $userA = $this->createUser('usera@example.com');
        $account = $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0006', $userA);

        // User B has pending invitation (not accepted yet)
        $userB = $this->createUser('userb@example.com');
        $accountUser = new AccountUser();
        $accountUser->setAccount($account);
        $accountUser->setUser($userB);
        $accountUser->setRole(AccountUserRole::SHARED);
        $accountUser->setStatus(AccountUserStatus::PENDING);
        $this->entityManager->persist($accountUser);
        $this->entityManager->flush();

        // User B should not be able to access (invitation not accepted)
        $this->expectException(AccountAccessDeniedException::class);
        $this->accountService->getOrCreateAccountByNumberForUser('NL91TEST0006', $userB);
    }

    public function testAccountNumberMaskingInException(): void
    {
        // User A creates account
        $userA = $this->createUser('usera@example.com');
        $this->accountService->getOrCreateAccountByNumberForUser('NL91ABNA0417164300', $userA);

        // User B tries to access
        $userB = $this->createUser('userb@example.com');

        try {
            $this->accountService->getOrCreateAccountByNumberForUser('NL91ABNA0417164300', $userB);
            $this->fail('Expected AccountAccessDeniedException was not thrown');
        } catch (AccountAccessDeniedException $e) {
            // Account number should be masked (NL91********4300)
            $this->assertStringContainsString('NL91****', $e->getMessage());
            $this->assertStringContainsString('4300', $e->getMessage());
            $this->assertStringNotContainsString('ABNA', $e->getMessage());
        }
    }

    // --- Helper Methods ---

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function addSharedUser(Account $account, User $user): AccountUser
    {
        $accountUser = new AccountUser();
        $accountUser->setAccount($account);
        $accountUser->setUser($user);
        $accountUser->setRole(AccountUserRole::SHARED);
        $accountUser->setStatus(AccountUserStatus::ACTIVE);

        $this->entityManager->persist($accountUser);
        $this->entityManager->flush();

        // Refresh account to sync the relationship
        $this->entityManager->refresh($account);

        return $accountUser;
    }
}
