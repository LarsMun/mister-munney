import React, { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { API_URL } from '../../lib/api';

interface FeatureFlags {
    living_dashboard: boolean;
    projects: boolean;
    external_payments: boolean;
    behavioral_insights: boolean;
}

interface FeatureFlagContextType {
    flags: FeatureFlags;
    isLoading: boolean;
    isEnabled: (flagName: keyof FeatureFlags) => boolean;
}

const FeatureFlagContext = createContext<FeatureFlagContextType | undefined>(undefined);

export function FeatureFlagProvider({ children }: { children: ReactNode }) {
    const [flags, setFlags] = useState<FeatureFlags>({
        living_dashboard: true, // Default to true in dev
        projects: true,
        external_payments: true,
        behavioral_insights: true,
    });
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        // Fetch feature flags from backend
        // For now, we'll default to all enabled
        // In a real implementation, you'd fetch from /api/feature-flags

        const loadFlags = async () => {
            try {
                // Placeholder - in real impl, fetch from backend
                // const response = await fetch(`${API_URL}/api/feature-flags`);
                // const data = await response.json();
                // setFlags(data);

                // For now, use defaults (all enabled)
                setFlags({
                    living_dashboard: true,
                    projects: true,
                    external_payments: true,
                    behavioral_insights: true,
                });
            } catch (error) {
                console.error('Failed to load feature flags:', error);
                // Keep defaults on error
            } finally {
                setIsLoading(false);
            }
        };

        loadFlags();
    }, []);

    const isEnabled = (flagName: keyof FeatureFlags): boolean => {
        return flags[flagName] ?? false;
    };

    return (
        <FeatureFlagContext.Provider value={{ flags, isLoading, isEnabled }}>
            {children}
        </FeatureFlagContext.Provider>
    );
}

export function useFeatureFlag(flagName: keyof FeatureFlags): boolean {
    const context = useContext(FeatureFlagContext);

    if (context === undefined) {
        throw new Error('useFeatureFlag must be used within a FeatureFlagProvider');
    }

    return context.isEnabled(flagName);
}

export function useFeatureFlags() {
    const context = useContext(FeatureFlagContext);

    if (context === undefined) {
        throw new Error('useFeatureFlags must be used within a FeatureFlagProvider');
    }

    return context;
}
