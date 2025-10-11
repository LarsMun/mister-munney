import { useState, useEffect } from 'react';
import { useTransactions } from '../../transactions/hooks/useTransactions';
import { useMonthlyStatistics } from '../../transactions/hooks/useMonthlyStatistics';

export function useDashboardData(accountId: number | null) {
    const {
        summary,
        transactions,
        refresh,
        importTransactions,
    } = useTransactions();

    const { statistics } = useMonthlyStatistics(accountId, 12);

    // Calculate insights
    const [insights, setInsights] = useState<Array<{
        type: 'warning' | 'success' | 'info' | 'tip';
        message: string;
        icon: string;
    }>>([]);

    useEffect(() => {
        if (!summary || !statistics) return;

        const newInsights = [];

        // Uncategorized transactions warning
        const uncategorized = transactions.filter(t => !t.category).length;
        if (uncategorized > 0) {
            newInsights.push({
                type: 'info' as const,
                message: `${uncategorized} transactie${uncategorized !== 1 ? 's' : ''} wacht${uncategorized === 1 ? '' : 'en'} op categorisatie`,
                icon: '📍'
            });
        }

        // Compare current month to average
        if (statistics.trimmedMean > 0) {
            const currentMonthExpenses = Math.abs(Number(summary.total_debit));
            const percentageDiff = ((currentMonthExpenses - statistics.trimmedMean) / statistics.trimmedMean) * 100;
            
            if (percentageDiff > 20) {
                newInsights.push({
                    type: 'warning' as const,
                    message: `Je uitgaven liggen ${percentageDiff.toFixed(0)}% hoger dan gemiddeld deze maand`,
                    icon: '⚠️'
                });
            } else if (percentageDiff < -10) {
                newInsights.push({
                    type: 'success' as const,
                    message: `Goed bezig! Je uitgaven liggen ${Math.abs(percentageDiff).toFixed(0)}% lager dan gemiddeld`,
                    icon: '✅'
                });
            }
        }

        // Positive net total
        const netTotal = Number(summary.net_total);
        if (netTotal > 0) {
            newInsights.push({
                type: 'success' as const,
                message: `Je hebt deze periode ${Math.abs(netTotal).toFixed(2)} euro gespaard!`,
                icon: '💰'
            });
        }

        // Tip for pattern matching
        if (uncategorized > 10) {
            newInsights.push({
                type: 'tip' as const,
                message: 'Tip: Maak patronen aan om transacties automatisch te categoriseren',
                icon: '💡'
            });
        }

        setInsights(newInsights);
    }, [summary, statistics, transactions]);

    return {
        summary,
        statistics,
        transactions,
        insights,
        refresh,
        importTransactions,
    };
}
