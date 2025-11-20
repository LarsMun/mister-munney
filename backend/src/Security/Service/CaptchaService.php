<?php

namespace App\Security\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for verifying hCaptcha tokens and tracking CAPTCHA requirement
 */
readonly class CaptchaService
{
    private const HCAPTCHA_VERIFY_URL = 'https://api.hcaptcha.com/siteverify';
    private const CAPTCHA_THRESHOLD = 3; // Show CAPTCHA after 3 failed attempts

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoginAttemptService $loginAttemptService,
        private string $hcaptchaSecretKey,
        private LoggerInterface $logger
    ) {}

    /**
     * Check if CAPTCHA is required for this email
     * Returns true if user has 3+ failed attempts in the last hour
     */
    public function needsCaptcha(string $email): bool
    {
        $failedCount = $this->loginAttemptService->getFailedAttemptsCount($email);
        return $failedCount >= self::CAPTCHA_THRESHOLD;
    }

    /**
     * Verify hCaptcha token with hCaptcha API
     *
     * @param string|null $token The hCaptcha response token from frontend
     * @param string|null $remoteIp The user's IP address
     * @return bool True if CAPTCHA verification succeeded
     */
    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        if (empty($token)) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', self::HCAPTCHA_VERIFY_URL, [
                'body' => [
                    'secret' => $this->hcaptchaSecretKey,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ],
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                $this->logger->info('hCaptcha verification succeeded', [
                    'ip' => $remoteIp,
                ]);
                return true;
            }

            $this->logger->warning('hCaptcha verification failed', [
                'ip' => $remoteIp,
                'error_codes' => $data['error-codes'] ?? [],
            ]);

            return false;
        } catch (\Exception $e) {
            $this->logger->error('hCaptcha verification error', [
                'error' => $e->getMessage(),
                'ip' => $remoteIp,
            ]);

            return false;
        }
    }

    /**
     * Get the CAPTCHA threshold (number of failed attempts before CAPTCHA is required)
     */
    public function getCaptchaThreshold(): int
    {
        return self::CAPTCHA_THRESHOLD;
    }
}
