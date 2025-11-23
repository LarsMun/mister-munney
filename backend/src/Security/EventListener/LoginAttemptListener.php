<?php

namespace App\Security\EventListener;

use App\Security\Service\AccountLockService;
use App\Security\Service\CaptchaService;
use App\Security\Service\LoginAttemptService;
use App\User\Repository\UserRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Event listener that tracks login attempts and enforces account lockout
 */
readonly class LoginAttemptListener
{
    public function __construct(
        private LoginAttemptService $loginAttemptService,
        private AccountLockService $accountLockService,
        private CaptchaService $captchaService,
        private UserRepository $userRepository
    ) {}

    /**
     * Check if account is locked and verify CAPTCHA if required
     * Priority 10: Run after rate limit listener (priority 15) but before authentication
     */
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
    public function checkAccountLock(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only check on login endpoint
        if ($request->getPathInfo() !== '/api/login' || $request->getMethod() !== 'POST') {
            return;
        }

        // Get email from request
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return;
        }

        // Check if user exists and is locked
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            // Don't reveal if user exists - let authentication handle it
            return;
        }

        if ($this->accountLockService->isAccountLocked($user)) {
            // Account is locked - reject immediately
            $response = new JsonResponse([
                'error' => 'Account is locked',
                'message' => 'Your account has been locked due to too many failed login attempts. Please check your email for an unlock link.',
                'locked' => true
            ], 403);

            $event->setResponse($response);
            return;
        }

        // Check if CAPTCHA is required (3+ failed attempts)
        if ($this->captchaService->needsCaptcha($email)) {
            $captchaToken = $data['captchaToken'] ?? null;
            $clientIp = $request->getClientIp();

            // If CAPTCHA is required but not provided or invalid, reject request
            if (!$this->captchaService->verify($captchaToken, $clientIp)) {
                $response = new JsonResponse([
                    'error' => 'CAPTCHA required',
                    'message' => 'Please complete the CAPTCHA verification.',
                    'requiresCaptcha' => true,
                    'failedAttempts' => $this->loginAttemptService->getFailedAttemptsCount($email)
                ], 400);

                $event->setResponse($response);
            }
        }
    }

    /**
     * Record successful login and clear failed attempts
     */
    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $request = $event->getRequest();

        $email = $user->getUserIdentifier();
        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        // Record successful login attempt
        $this->loginAttemptService->recordAttempt($email, true, $ipAddress, $userAgent);

        // Clear failed attempts to immediately reset CAPTCHA requirement
        $this->loginAttemptService->clearAttempts($email);
    }

    /**
     * Record failed login and lock account if threshold reached
     */
    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        // Get email from request
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return;
        }

        $ipAddress = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        // Record failed login attempt
        $this->loginAttemptService->recordAttempt($email, false, $ipAddress, $userAgent);

        // Check if account should be locked
        if ($this->loginAttemptService->shouldLockAccount($email)) {
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user && !$this->accountLockService->isAccountLocked($user)) {
                // Lock account and send unlock email
                $this->accountLockService->lockAccount($user, $ipAddress ?? 'unknown');
            }
        }
    }

    /**
     * Modify response for locked accounts to include helpful information
     * Priority -10: Run after response is generated but before it's sent
     */
    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -10)]
    public function modifyLockedAccountResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only modify responses for login endpoint
        if ($request->getPathInfo() !== '/api/login' || $request->getMethod() !== 'POST') {
            return;
        }

        // Check if this is an authentication failure (401)
        if ($response->getStatusCode() !== 401) {
            return;
        }

        // Get email from request
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return;
        }

        // Check remaining attempts before lockout
        $failedCount = $this->loginAttemptService->getFailedAttemptsCount($email);
        $remainingAttempts = 5 - $failedCount;
        $requiresCaptcha = $this->captchaService->needsCaptcha($email);

        $responseData = json_decode($response->getContent(), true) ?? [];

        // Add CAPTCHA requirement flag
        if ($requiresCaptcha) {
            $responseData['requiresCaptcha'] = true;
        }

        // Add failed attempts count
        $responseData['failedAttempts'] = $failedCount;

        if ($remainingAttempts > 0 && $remainingAttempts <= 2) {
            // Warn user they're close to lockout
            $responseData['warning'] = sprintf(
                'Warning: %d failed login attempt%s remaining before account lockout.',
                $remainingAttempts,
                $remainingAttempts === 1 ? '' : 's'
            );
            $responseData['remainingAttempts'] = $remainingAttempts;
        }

        $response->setContent(json_encode($responseData));
    }
}
