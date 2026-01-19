<?php

namespace App\Tests\Unit\Security\Service;

use App\Security\Entity\AuditLog;
use App\Security\Repository\AuditLogRepository;
use App\Security\Service\AuditLogService;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AuditLogServiceTest extends TestCase
{
    private AuditLogService $auditLogService;
    private MockObject $entityManager;
    private MockObject $auditLogRepository;
    private MockObject $requestStack;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->auditLogRepository = $this->createMock(AuditLogRepository::class);
        $this->requestStack = $this->createMock(RequestStack::class);

        $this->auditLogService = new AuditLogService(
            $this->entityManager,
            $this->auditLogRepository,
            $this->requestStack
        );
    }

    public function testLogCreatesAuditLogEntry(): void
    {
        // Given
        $action = AuditLog::ACTION_LOGIN;

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AuditLog $auditLog) use ($action) {
                return $auditLog->getAction() === $action;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->log($action);

        // Then
        $this->assertInstanceOf(AuditLog::class, $result);
        $this->assertEquals($action, $result->getAction());
    }

    public function testLogWithRequestInformation(): void
    {
        // Given
        $action = AuditLog::ACTION_LOGIN;
        $ipAddress = '192.168.1.1';
        $userAgent = 'Test Browser/1.0';

        $request = $this->createMock(Request::class);
        $request->method('getClientIp')->willReturn($ipAddress);
        $request->headers = new \Symfony\Component\HttpFoundation\HeaderBag([
            'User-Agent' => $userAgent
        ]);

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $this->entityManager->expects($this->once())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->log($action);

        // Then
        $this->assertEquals($ipAddress, $result->getIpAddress());
        $this->assertEquals($userAgent, $result->getUserAgent());
    }

    public function testLogLoginCreatesLoginAuditEntry(): void
    {
        // Given
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AuditLog $auditLog) {
                return $auditLog->getAction() === AuditLog::ACTION_LOGIN
                    && $auditLog->getEntityType() === 'User'
                    && $auditLog->getEntityId() === 1;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->logLogin($user);

        // Then
        $this->assertEquals(AuditLog::ACTION_LOGIN, $result->getAction());
        $this->assertEquals('User', $result->getEntityType());
    }

    public function testLogFailedLoginMasksEmail(): void
    {
        // Given
        $email = 'test@example.com';

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $capturedAuditLog = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AuditLog $auditLog) use (&$capturedAuditLog) {
                $capturedAuditLog = $auditLog;
                return true;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->logFailedLogin($email);

        // Then
        $this->assertEquals(AuditLog::ACTION_LOGIN_FAILED, $result->getAction());
        $details = $result->getDetails();
        $this->assertArrayHasKey('email', $details);
        // Email should be masked (t**t@example.com)
        $this->assertNotEquals($email, $details['email']);
        $this->assertStringContainsString('@example.com', $details['email']);
    }

    public function testLogAccountLockedEntry(): void
    {
        // Given
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AuditLog $auditLog) {
                return $auditLog->getAction() === AuditLog::ACTION_ACCOUNT_LOCKED;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->logAccountLocked($user);

        // Then
        $this->assertEquals(AuditLog::ACTION_ACCOUNT_LOCKED, $result->getAction());
    }

    public function testLogTransactionImportWithDetails(): void
    {
        // Given
        $user = $this->createMock(User::class);
        $accountId = 5;
        $count = 100;

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AuditLog $auditLog) use ($accountId, $count) {
                return $auditLog->getAction() === AuditLog::ACTION_TRANSACTION_IMPORTED
                    && $auditLog->getEntityType() === 'Account'
                    && $auditLog->getEntityId() === $accountId
                    && $auditLog->getDetails()['count'] === $count;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->logTransactionImport($user, $accountId, $count);

        // Then
        $this->assertEquals(AuditLog::ACTION_TRANSACTION_IMPORTED, $result->getAction());
        $this->assertEquals(['count' => $count], $result->getDetails());
    }

    public function testGetRecentLogsDelegatesToRepository(): void
    {
        // Given
        $user = $this->createMock(User::class);
        $expectedLogs = [new AuditLog(), new AuditLog()];

        $this->auditLogRepository->expects($this->once())
            ->method('findByUser')
            ->with($user, 100)
            ->willReturn($expectedLogs);

        // When
        $result = $this->auditLogService->getRecentLogs($user);

        // Then
        $this->assertCount(2, $result);
    }

    public function testCleanupOldLogsDelegatesToRepository(): void
    {
        // Given
        $deletedCount = 50;

        $this->auditLogRepository->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTime $date) {
                // Should be approximately 90 days ago
                $diff = (new \DateTime())->diff($date);
                return $diff->days >= 89 && $diff->days <= 91;
            }))
            ->willReturn($deletedCount);

        // When
        $result = $this->auditLogService->cleanupOldLogs(90);

        // Then
        $this->assertEquals($deletedCount, $result);
    }

    public function testLogDataExportIncludesTypeAndCount(): void
    {
        // Given
        $user = $this->createMock(User::class);
        $exportType = 'csv';
        $recordCount = 500;

        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AuditLog $auditLog) use ($exportType, $recordCount) {
                $details = $auditLog->getDetails();
                return $auditLog->getAction() === AuditLog::ACTION_DATA_EXPORT
                    && $details['type'] === $exportType
                    && $details['count'] === $recordCount;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // When
        $result = $this->auditLogService->logDataExport($user, $exportType, $recordCount);

        // Then
        $this->assertEquals(AuditLog::ACTION_DATA_EXPORT, $result->getAction());
    }
}
