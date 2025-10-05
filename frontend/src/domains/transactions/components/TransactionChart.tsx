import React, { useState } from "react";
import {
    LineChart,
    Line,
    XAxis,
    YAxis,
    Tooltip,
    ResponsiveContainer,
    ReferenceLine,
    ReferenceArea,
    TooltipProps,
} from "recharts";
import { formatDate } from "../../../shared/utils/DateFormat";
import { formatMoney } from "../../../shared/utils/MoneyFormat";

// Labelen en kleuren van de grafieklijnen
const CHART_LABELS = { value: "Balans", debitTotal: "Uitgaven", creditTotal: "Inkomsten" };
const CHART_COLORS = { value: "#3b82f6", debitTotal: "#ef4444", creditTotal: "#22c55e" };

interface DataPoint {
    date: string;
    value: number;
    debitTotal: number;
    creditTotal: number;
}

interface Props {
    data: DataPoint[];
    onSelectRange?: (start: string | null, end: string | null) => void;
}

interface CustomTooltipProps extends TooltipProps<any, any> {
    isSelecting: boolean;
    startDate: string | null;
    endDate: string | null;
    finalSelection: { start: string; end: string } | null;
    data: DataPoint[];
}

export default function TransactionChart({ data, onSelectRange }: Props) {
    const [visibleLines, setVisibleLines] = useState({
        value: true,
        debitTotal: true,
        creditTotal: true,
    });

    const [isSelecting, setIsSelecting] = useState(false);
    const [startDate, setStartDate] = useState<string | null>(null);
    const [endDate, setEndDate] = useState<string | null>(null);
    const [finalSelection, setFinalSelection] = useState<{ start: string; end: string } | null>(null);

    const toggleLine = (key: keyof typeof visibleLines) => {
        setVisibleLines((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const handleMouseDown = (e: any) => {
        if (e && e.activeLabel) {
            setIsSelecting(true);
            setStartDate(e.activeLabel);
            setEndDate(null);
        }
    };

    const handleMouseMove = (e: any) => {
        if (!isSelecting || !startDate) return;
        if (e && e.activeLabel) {
            setEndDate(e.activeLabel);
        }
    };

    const handleMouseUp = () => {
        if (startDate && endDate) {
            const start = startDate < endDate ? startDate : endDate;
            const end = startDate > endDate ? startDate : endDate;
            setFinalSelection({ start, end });

            if (onSelectRange) {
                onSelectRange(start, end);
            }
        } else if (onSelectRange) {
            onSelectRange(null, null);
        }

        setIsSelecting(false);
        setStartDate(null);
        setEndDate(null);
    };

    const clearSelection = () => {
        setFinalSelection(null);
        if (onSelectRange) {
            onSelectRange(null, null);
        }
    };

    function CustomTooltip({
                               active,
                               payload,
                               isSelecting,
                               startDate,
                               endDate,
                               finalSelection,
                               data,
                           }: CustomTooltipProps) {
        let selectedStart = null;
        let selectedEnd = null;

        if (isSelecting && startDate && endDate) {
            selectedStart = startDate < endDate ? startDate : endDate;
            selectedEnd = startDate > endDate ? startDate : endDate;
        } else if (finalSelection) {
            selectedStart = finalSelection.start;
            selectedEnd = finalSelection.end;
        }

        if (selectedStart && selectedEnd) {
            const selectedData = data.filter(d => d.date >= selectedStart! && d.date <= selectedEnd!);
            const totalCredits = selectedData.reduce((sum, d) => sum + d.creditTotal, 0);
            const totalDebits = selectedData.reduce((sum, d) => sum + d.debitTotal, 0);

            return (
                <div className="bg-white p-2 border rounded shadow text-xs">
                    <div><strong>Van:</strong> {formatDate(selectedStart)}</div>
                    <div><strong>t/m:</strong> {formatDate(selectedEnd)}</div>
                    <div className="mt-2"><strong>Inkomsten:</strong> {formatMoney(totalCredits)}</div>
                    <div><strong>Uitgaven:</strong> {formatMoney(totalDebits)}</div>
                </div>
            );
        }

        if (active && payload && payload.length) {
            return (
                <div className="bg-white p-2 border rounded shadow text-xs">
                    {payload.map((entry, index) => (
                        <div key={index}>
                            <span className="font-semibold">{CHART_LABELS[entry.name]}</span>: {formatMoney(entry.value)}
                        </div>
                    ))}
                </div>
            );
        }

        return null;
    }

    return (
        <div className="my-4">
            <h2 className="text-sm text-gray-500 mb-2">Dagelijkse balans</h2>

            <div className="w-full h-48 bg-white border border-gray-200 rounded-lg h-80">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={data}
                        onMouseDown={handleMouseDown}
                        onMouseMove={handleMouseMove}
                        onMouseUp={handleMouseUp}
                        margin={{ top: 10, right: 10, left: 0, bottom: 0 }}
                    >
                        <XAxis dataKey="date" tickFormatter={formatDate} fontSize={10} fontWeight={600} />
                        <YAxis fontSize={10} domain={['auto', 'auto']} includeZero tickFormatter={(value) => formatMoney(value)} />
                        <ReferenceLine y={0} stroke="#999" strokeDasharray="3 3" />
                        <Tooltip
                            content={
                                <CustomTooltip
                                    isSelecting={isSelecting}
                                    startDate={startDate}
                                    endDate={endDate}
                                    finalSelection={finalSelection}
                                    data={data}
                                />
                            }
                        />

                        {visibleLines.value && (
                            <Line type="monotone" dataKey="value" stroke={CHART_COLORS.value} strokeWidth={2} dot={false} />
                        )}
                        {visibleLines.debitTotal && (
                            <Line type="monotone" dataKey="debitTotal" stroke={CHART_COLORS.debitTotal} strokeWidth={2} dot={false} />
                        )}
                        {visibleLines.creditTotal && (
                            <Line type="monotone" dataKey="creditTotal" stroke={CHART_COLORS.creditTotal} strokeWidth={2} dot={false} />
                        )}

                        {isSelecting && startDate && endDate && (
                            <ReferenceArea
                                x1={startDate}
                                x2={endDate}
                                strokeOpacity={0.3}
                                fill="#3b82f6"
                                fillOpacity={0.2}
                            />
                        )}
                    </LineChart>
                </ResponsiveContainer>

                {/* ✨ Legenda */}
                <div className="flex gap-4 mt-2 text-sm text-gray-600 justify-end">
                    {Object.keys(CHART_LABELS).map((key) => (
                        <button
                            key={key}
                            onClick={() => toggleLine(key as keyof typeof visibleLines)}
                            className={`flex items-center gap-1 px-2 py-1 rounded border ${
                                visibleLines[key as keyof typeof visibleLines]
                                    ? "bg-white border-gray-300"
                                    : "bg-gray-300 border-gray-200 text-gray-400"
                            }`}
                        >
                            <span
                                className="inline-block w-3 h-1 rounded-full"
                                style={{ backgroundColor: CHART_COLORS[key] }}
                            />
                            {CHART_LABELS[key]}
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}