type ConfidenceIndicatorProps = {
    score: number;
    showLabel?: boolean;
};

export function ConfidenceIndicator({ score, showLabel = true }: ConfidenceIndicatorProps) {
    const percentage = Math.round(score * 100);

    // Determine color based on score
    let colorClass = 'bg-gray-200';
    let textClass = 'text-gray-600';

    if (score >= 0.9) {
        colorClass = 'bg-green-500';
        textClass = 'text-green-700';
    } else if (score >= 0.8) {
        colorClass = 'bg-green-400';
        textClass = 'text-green-600';
    } else if (score >= 0.7) {
        colorClass = 'bg-yellow-400';
        textClass = 'text-yellow-700';
    } else {
        colorClass = 'bg-orange-400';
        textClass = 'text-orange-700';
    }

    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden max-w-16">
                <div
                    className={`h-full ${colorClass} rounded-full transition-all`}
                    style={{ width: `${percentage}%` }}
                />
            </div>
            {showLabel && (
                <span className={`text-xs font-medium ${textClass}`}>{percentage}%</span>
            )}
        </div>
    );
}
