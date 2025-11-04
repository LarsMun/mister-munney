<?php

namespace App\FeatureFlag\Service;

use App\FeatureFlag\Repository\FeatureFlagRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class FeatureFlagService
{
    private array $cache = [];

    public function __construct(
        private readonly FeatureFlagRepository $featureFlagRepository,
        private readonly ParameterBagInterface $params
    ) {
    }

    /**
     * Check if a feature flag is enabled
     * Priority: 1. Environment variable, 2. Database, 3. Default (false)
     */
    public function isEnabled(string $flagName): bool
    {
        // Check cache first
        if (isset($this->cache[$flagName])) {
            return $this->cache[$flagName];
        }

        // Check environment variable first (FEATURE_LIVING_DASHBOARD=true)
        $envKey = 'FEATURE_' . strtoupper($flagName);
        $envValue = $_ENV[$envKey] ?? null;

        if ($envValue !== null) {
            $enabled = filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
            $this->cache[$flagName] = $enabled;
            return $enabled;
        }

        // Fallback to database
        $featureFlag = $this->featureFlagRepository->findByName($flagName);

        if ($featureFlag !== null) {
            $enabled = $featureFlag->isEnabled();
            $this->cache[$flagName] = $enabled;
            return $enabled;
        }

        // Default to false if not found
        $this->cache[$flagName] = false;
        return false;
    }

    /**
     * Get all feature flags (database only, ignores env overrides)
     */
    public function getAll(): array
    {
        $flags = [];
        $featureFlags = $this->featureFlagRepository->findAll();

        foreach ($featureFlags as $flag) {
            $flags[$flag->getName()] = [
                'enabled' => $flag->isEnabled(),
                'description' => $flag->getDescription(),
            ];
        }

        return $flags;
    }

    /**
     * Clear the cache (useful for testing)
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}
