import type { RecurrenceFrequency } from '../models/RecurringTransaction';
import { FREQUENCY_LABELS } from '../models/RecurringTransaction';

type FrequencyBadgeProps = {
    frequency: RecurrenceFrequency;
    size?: 'sm' | 'md';
};

const FREQUENCY_COLORS: Record<RecurrenceFrequency, string> = {
    weekly: 'bg-purple-100 text-purple-800',
    biweekly: 'bg-indigo-100 text-indigo-800',
    monthly: 'bg-blue-100 text-blue-800',
    quarterly: 'bg-teal-100 text-teal-800',
    yearly: 'bg-green-100 text-green-800',
};

export function FrequencyBadge({ frequency, size = 'md' }: FrequencyBadgeProps) {
    const sizeClasses = size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm';

    return (
        <span
            className={`inline-flex items-center rounded-full font-medium ${FREQUENCY_COLORS[frequency]} ${sizeClasses}`}
        >
            {FREQUENCY_LABELS[frequency]}
        </span>
    );
}
