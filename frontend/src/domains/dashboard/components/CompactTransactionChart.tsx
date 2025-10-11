import { useState } from "react";
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    ReferenceLine,
    TooltipProps,
} from "recharts";
import { formatDate } from "../../../shared/utils/DateFormat";
import { formatMoney } from "../../../shared/utils/MoneyFormat";

type ChartKey = 'value' | 'debitTotal' | 'creditTotal';

const CHART_LABELS: Record<ChartKey, string> = { 
    value: "Balans", 
    debitTotal: "Uitgaven", 
    creditTotal: "Inkomsten" 
};

const CHART_COLORS: Record<ChartKey, string> = { 
    value: "#3b82f6", 
    debitTotal: "#ef4444", 
    creditTotal: "#22c55e" 
};

interface DataPoint {
    date: string;
    value: number;
    debitTotal: number;
    creditTotal: number;
}

interface Props {
    data: DataPoint[];
    title?: string;
    height?: number;
}

interface CustomTooltipProps extends TooltipProps<number, string> {}

export default function CompactTransactionChart({ data, title = "Dagelijkse Balans", height = 300 }: Props) {
    const [visibleLines, setVisibleLines] = useState<Record<ChartKey, boolean>>({
        value: true,
        debitTotal: true,
        creditTotal: true,
    });

    const toggleLine = (key: ChartKey) => {
        setVisibleLines((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    function CustomTooltip({ active, payload }: CustomTooltipProps) {
        if (active && payload && payload.length) {
            return (
                <div className="bg-white p-3 border border-gray-200 rounded-lg shadow-lg text-sm">
                    <p className="font-semibold text-gray-700 mb-2">
                        {formatDate(payload[0].payload.date)}
                    </p>
                    {payload.map((entry, index) => {
                        const key = entry.name as ChartKey;
                        return (
                            <div key={index} className="flex items-center justify-between gap-4">
                                <span className="flex items-center gap-2">
                                    <span 
                                        className="inline-block w-3 h-3 rounded-full" 
                                        style={{ backgroundColor: CHART_COLORS[key] }}
                                    />
                                    <span className="text-gray-600">{CHART_LABELS[key]}</span>
                                </span>
                                <span className="font-semibold text-gray-900">
                                    {formatMoney(entry.value as number)}
                                </span>
                            </div>
                        );
                    })}
                </div>
            );
        }

        return null;
    }

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <div className="flex justify-between items-center mb-4">
                <h3 className="text-lg font-semibold text-gray-800">{title}</h3>
                <div className="flex gap-2">
                    {(Object.keys(CHART_LABELS) as ChartKey[]).map((key) => (
                        <button
                            key={key}
                            onClick={() => toggleLine(key)}
                            className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm transition-all ${
                                visibleLines[key]
                                    ? "bg-white border-gray-300 shadow-sm hover:shadow"
                                    : "bg-gray-100 border-gray-200 text-gray-400"
                            }`}
                        >
                            <span
                                className="inline-block w-3 h-1.5 rounded-full"
                                style={{ backgroundColor: CHART_COLORS[key] }}
                            />
                            {CHART_LABELS[key]}
                        </button>
                    ))}
                </div>
            </div>

            <div style={{ width: '100%', height }}>
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={data}
                        margin={{ top: 5, right: 10, left: 0, bottom: 5 }}
                    >
                        <XAxis 
                            dataKey="date" 
                            tickFormatter={formatDate} 
                            fontSize={11} 
                            fontWeight={600}
                            stroke="#6b7280"
                        />
                        <YAxis 
                            fontSize={11} 
                            domain={['auto', 'auto']} 
                            tickFormatter={(value) => formatMoney(value)}
                            stroke="#6b7280"
                        />
                        <ReferenceLine y={0} stroke="#9ca3af" strokeDasharray="3 3" />
                        <Tooltip content={<CustomTooltip />} />

                        {visibleLines.value && (
                            <Line 
                                type="monotone" 
                                dataKey="value" 
                                stroke={CHART_COLORS.value} 
                                strokeWidth={2.5} 
                                dot={false}
                                activeDot={{ r: 6 }}
                            />
                        )}
                        {visibleLines.debitTotal && (
                            <Line 
                                type="monotone" 
                                dataKey="debitTotal" 
                                stroke={CHART_COLORS.debitTotal} 
                                strokeWidth={2.5} 
                                dot={false}
                                activeDot={{ r: 6 }}
                            />
                        )}
                        {visibleLines.creditTotal && (
                            <Line 
                                type="monotone" 
                                dataKey="creditTotal" 
                                stroke={CHART_COLORS.creditTotal} 
                                strokeWidth={2.5} 
                                dot={false}
                                activeDot={{ r: 6 }}
                            />
                        )}
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
