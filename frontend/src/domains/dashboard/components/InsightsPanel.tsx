import { motion } from 'framer-motion';

interface Insight {
    type: 'warning' | 'success' | 'info' | 'tip';
    message: string;
    icon: string;
}

interface InsightsPanelProps {
    insights: Insight[];
}

export default function InsightsPanel({ insights }: InsightsPanelProps) {
    const getInsightStyle = (type: Insight['type']) => {
        switch (type) {
            case 'warning':
                return 'bg-orange-50 border-orange-200 text-orange-800';
            case 'success':
                return 'bg-green-50 border-green-200 text-green-800';
            case 'info':
                return 'bg-blue-50 border-blue-200 text-blue-800';
            case 'tip':
                return 'bg-purple-50 border-purple-200 text-purple-800';
        }
    };

    if (insights.length === 0) {
        return null;
    }

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-lg font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <span>🔔</span>
                <span>Inzichten & Waarschuwingen</span>
            </h3>
            <div className="space-y-3">
                {insights.map((insight, index) => (
                    <motion.div
                        key={index}
                        initial={{ opacity: 0, x: -20 }}
                        animate={{ opacity: 1, x: 0 }}
                        transition={{ duration: 0.3, delay: index * 0.1 }}
                        className={`${getInsightStyle(insight.type)} border-2 rounded-lg p-4 flex items-start gap-3`}
                    >
                        <span className="text-2xl flex-shrink-0">{insight.icon}</span>
                        <p className="text-sm font-medium flex-grow">{insight.message}</p>
                    </motion.div>
                ))}
            </div>
        </div>
    );
}
