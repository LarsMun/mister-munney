<?php

namespace App\Tests\Unit\Account\Service;

use App\Account\Service\AccountSharingService;
use App\Entity\Account;
use App\Entity\AccountUser;
use App\Enum\AccountUserRole;
use App\Enum\AccountUserStatus;
use App\Tests\TestCase\DatabaseTestCase;
use App\User\Entity\User;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException as ForbiddenHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Unit tests for AccountSharingService
 */
class AccountSharingServiceTest extends DatabaseTestCase
{
    private AccountSharingService $sharingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sharingService = $this->container->get(AccountSharingService::class);
    }

    public function testShareAccountCreatesInvitation(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0001', $owner);
        $sharedUser = $this->createUser('shared@example.com');

        $accountUser = $this->sharingService->shareAccount($account, $owner, 'shared@example.com');

        $this->assertNotNull($accountUser);
        $this->assertEquals($sharedUser->getId(), $accountUser->getUser()->getId());
        $this->assertEquals(AccountUserRole::SHARED, $accountUser->getRole());
        $this->assertEquals(AccountUserStatus::PENDING, $accountUser->getStatus());
        $this->assertEquals($owner->getId(), $accountUser->getInvitedBy()->getId());
        $this->assertNotNull($accountUser->getInvitedAt());
        $this->assertNotNull($accountUser->getExpiresAt());

        // Expiry should be 7 days from now
        $expectedExpiry = new \DateTimeImmutable('+7 days');
        $actualExpiry = $accountUser->getExpiresAt();
        $diff = $expectedExpiry->getTimestamp() - $actualExpiry->getTimestamp();
        $this->assertLessThan(5, abs($diff)); // Within 5 seconds
    }

    public function testNonOwnerCannotShareAccount(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0002', $owner);

        $nonOwner = $this->createUser('nonowner@example.com');
        $this->addSharedUser($account, $nonOwner);

        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage('Only the account owner can share access');

        $this->sharingService->shareAccount($account, $nonOwner, 'someone@example.com');
    }

    public function testShareAccountWithNonExistentUserReturnsNull(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0003', $owner);

        // Anti-enumeration: should return null if user doesn't exist
        $result = $this->sharingService->shareAccount($account, $owner, 'nonexistent@example.com');

        $this->assertNull($result);
    }

    public function testCannotShareWithUserWhoAlreadyHasAccess(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0004', $owner);
        $sharedUser = $this->createUser('shared@example.com');
        $this->addSharedUser($account, $sharedUser);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('already has access');

        $this->sharingService->shareAccount($account, $owner, 'shared@example.com');
    }

    public function testCannotShareWithUserWhoPendingInvitation(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0005', $owner);
        $sharedUser = $this->createUser('shared@example.com');

        // Create pending invitation
        $this->sharingService->shareAccount($account, $owner, 'shared@example.com');

        // Try to share again
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('invitation for this user is already pending');

        $this->sharingService->shareAccount($account, $owner, 'shared@example.com');
    }

    public function testAcceptValidInvitation(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0006', $owner);
        $sharedUser = $this->createUser('shared@example.com');

        $accountUser = $this->sharingService->shareAccount($account, $owner, 'shared@example.com');

        // Accept invitation
        $this->sharingService->acceptInvitation($accountUser, $sharedUser);

        $this->assertEquals(AccountUserStatus::ACTIVE, $accountUser->getStatus());
        $this->assertNotNull($accountUser->getAcceptedAt());
        $this->assertNull($accountUser->getExpiresAt()); // No longer relevant

        // Refresh account to sync the relationship
        $this->entityManager->refresh($account);
        $this->assertTrue($account->hasAccess($sharedUser));
    }

    public function testCannotAcceptExpiredInvitation(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0007', $owner);
        $sharedUser = $this->createUser('shared@example.com');

        $accountUser = $this->sharingService->shareAccount($account, $owner, 'shared@example.com');

        // Manually expire invitation
        $accountUser->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->entityManager->flush();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('invitation has expired');

        $this->sharingService->acceptInvitation($accountUser, $sharedUser);
    }

    public function testCannotAcceptOtherUsersInvitation(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0008', $owner);
        $sharedUser = $this->createUser('shared@example.com');
        $otherUser = $this->createUser('other@example.com');

        $accountUser = $this->sharingService->shareAccount($account, $owner, 'shared@example.com');

        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage('cannot accept an invitation meant for another user');

        $this->sharingService->acceptInvitation($accountUser, $otherUser);
    }

    public function testCannotAcceptAlreadyAcceptedInvitation(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0009', $owner);
        $sharedUser = $this->createUser('shared@example.com');

        $accountUser = $this->sharingService->shareAccount($account, $owner, 'shared@example.com');
        $this->sharingService->acceptInvitation($accountUser, $sharedUser);

        // Try to accept again
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('invitation is not pending');

        $this->sharingService->acceptInvitation($accountUser, $sharedUser);
    }

    public function testRevokeSharedUserAccess(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0010', $owner);
        $sharedUser = $this->createUser('shared@example.com');
        $this->addSharedUser($account, $sharedUser);

        // Revoke access
        $this->sharingService->revokeAccess($account, $owner, $sharedUser);

        // Check revoked
        $this->assertFalse($account->hasAccess($sharedUser));
    }

    public function testCannotRevokeOwnerAccess(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0011', $owner);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Cannot revoke access for the account owner');

        $this->sharingService->revokeAccess($account, $owner, $owner);
    }

    public function testOnlyOwnerCanRevokeAccess(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0012', $owner);
        $sharedUser1 = $this->createUser('shared1@example.com');
        $sharedUser2 = $this->createUser('shared2@example.com');
        $this->addSharedUser($account, $sharedUser1);
        $this->addSharedUser($account, $sharedUser2);

        // Shared user 1 tries to revoke shared user 2
        $this->expectException(ForbiddenHttpException::class);
        $this->expectExceptionMessage('Only the account owner can revoke access');

        $this->sharingService->revokeAccess($account, $sharedUser1, $sharedUser2);
    }

    public function testRevokeNonExistentAccessThrowsException(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account = $this->createAccount('NL91TEST0013', $owner);
        $otherUser = $this->createUser('other@example.com');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('User does not have access to this account');

        $this->sharingService->revokeAccess($account, $owner, $otherUser);
    }

    public function testGetPendingInvitationsForUser(): void
    {
        $owner = $this->createUser('owner@example.com');
        $account1 = $this->createAccount('NL91TEST0014', $owner);
        $account2 = $this->createAccount('NL91TEST0015', $owner);
        $sharedUser = $this->createUser('shared@example.com');

        // Create 2 invitations
        $this->sharingService->shareAccount($account1, $owner, 'shared@example.com');
        $this->sharingService->shareAccount($account2, $owner, 'shared@example.com');

        $invitations = $this->sharingService->getPendingInvitationsForUser($sharedUser);

        $this->assertCount(2, $invitations);
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

    private function createAccount(string $accountNumber, User $owner): Account
    {
        $account = new Account();
        $account->setAccountNumber($accountNumber);
        $account->setName('Test Account');
        $account->addOwner($owner);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        return $account;
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
