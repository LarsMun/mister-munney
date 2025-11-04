import { BudgetInsight } from '../../budgets/models/AdaptiveBudget';

interface BehavioralInsightsPanelProps {
    insights: BudgetInsight[];
}

export default function BehavioralInsightsPanel({ insights }: BehavioralInsightsPanelProps) {
    if (insights.length === 0) {
        return null;
    }

    // Limit to max 3 insights as per spec
    const displayInsights = insights.slice(0, 3);

    return (
        <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <span aria-hidden="true">💡</span>
                <span>Gedragsinzichten</span>
            </h3>
            <p className="text-sm text-gray-700 mb-4">
                Gebaseerd op jouw uitgavepatroon van de afgelopen 6 maanden
            </p>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {displayInsights.map((insight, idx) => (
                    <InsightCard key={idx} insight={insight} />
                ))}
            </div>
        </div>
    );
}

interface InsightCardProps {
    insight: BudgetInsight;
}

function InsightCard({ insight }: InsightCardProps) {
    // Determine styling based on insight level
    const getCardStyle = () => {
        switch (insight.level) {
            case 'stable':
                return {
                    container: 'bg-gray-50 border-gray-200',
                    badge: 'bg-gray-100 text-gray-800',
                    icon: '✓',
                    iconLabel: 'Stabiel',
                };
            case 'slight':
                return {
                    container: 'bg-blue-50 border-blue-200',
                    badge: 'bg-blue-100 text-blue-800',
                    icon: '→',
                    iconLabel: 'Lichte afwijking',
                };
            case 'anomaly':
                return {
                    container: 'bg-orange-50 border-orange-200',
                    badge: 'bg-orange-100 text-orange-800',
                    icon: '⚠',
                    iconLabel: 'Significante afwijking',
                };
            default:
                return {
                    container: 'bg-gray-50 border-gray-200',
                    badge: 'bg-gray-100 text-gray-800',
                    icon: '•',
                    iconLabel: 'Onbekend',
                };
        }
    };

    const style = getCardStyle();

    return (
        <article className={`border-2 rounded-lg p-4 ${style.container} transition-all hover:shadow-md`}>
            {/* Header */}
            <div className="flex items-start justify-between mb-3">
                <h4 className="font-semibold text-gray-900 text-sm">{insight.budgetName}</h4>
                <span className={`text-xs px-2 py-1 rounded font-medium ${style.badge}`} aria-label={style.iconLabel}>
                    {style.icon}
                </span>
            </div>

            {/* Message - Neutral coaching copy */}
            <p className="text-sm text-gray-700 mb-3 leading-relaxed">{insight.message}</p>

            {/* Values */}
            <div className="space-y-1.5 text-xs">
                <div className="flex justify-between items-center">
                    <span className="text-gray-700">Huidig:</span>
                    <span className="font-semibold text-gray-900">{insight.current}</span>
                </div>
                <div className="flex justify-between items-center">
                    <span className="text-gray-700">Normaal:</span>
                    <span className="font-medium text-gray-900">{insight.normal}</span>
                </div>
                <div className="flex justify-between items-center pt-1.5 border-t border-gray-300">
                    <span className="text-gray-700">Verschil:</span>
                    <span className={`font-semibold ${
                        Math.abs(insight.deltaPercent) < 10 ? 'text-gray-700' :
                        insight.deltaPercent > 0 ? 'text-orange-700' :
                        'text-green-700'
                    }`}>
                        {insight.delta}
                    </span>
                </div>
            </div>
        </article>
    );
}
