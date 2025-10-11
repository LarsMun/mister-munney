// src/domains/transactions/components/PeriodPicker.tsx
import { useEffect, useState } from "react";
import {formatDateToLocalString, formatMonthFull} from "../../../shared/utils/DateFormat";

interface Props {
    months: string[];
    onChange: (startDate: string, endDate: string) => void;
}

export default function PeriodPicker({ months, onChange }: Props) {
    const [periodType, setPeriodType] = useState<"month" | "quarter" | "halfyear" | "year">("month");
    const [selectedYear, setSelectedYear] = useState<string>("");
    const [selectedMonth, setSelectedMonth] = useState<string>("01");
    const [selectedQuarter, setSelectedQuarter] = useState<"1" | "2" | "3" | "4">("1");
    const [selectedHalf, setSelectedHalf] = useState<"1" | "2">("1");
    const lastMonth = months.length > 0 ? months[0] : null;
    const [lastYear, lastMonthNumber] = lastMonth ? lastMonth.split("-").map(Number) : [null, null];
    const lastDate = lastYear && lastMonthNumber ? new Date(lastYear, lastMonthNumber - 1) : null;

    const availableYears = Array.from(new Set(months.map(m => m.split('-')[0]))).sort((a, b) => Number(b) - Number(a));
    const availableMonths = selectedYear
        ? months
            .filter(m => m.startsWith(selectedYear))
            .map(m => m.split('-')[1])
            .sort((a, b) => Number(a) - Number(b))
        : [];
    const availableQuarters = ["1", "2", "3", "4"].filter((quarter) => {
        if (!lastDate) return false;
        const quarterStartMonth = (Number(quarter) - 1) * 3;
        const start = new Date(Number(selectedYear), quarterStartMonth);
        return start <= lastDate;
    });
    const availableHalves = ["1", "2"].filter((half) => {
        if (!lastDate) return false;
        const halfStartMonth = half === "1" ? 0 : 6;
        const start = new Date(Number(selectedYear), halfStartMonth);
        return start <= lastDate;
    });

    // Zet automatisch nieuwste jaar & maand bij laden
    useEffect(() => {
        if (months.length > 0) {
            const [year, month] = months[0].split('-');
            setSelectedYear(year);
            setSelectedMonth(month);
        }
    }, [months]);

    // Reageer bij wijziging van selectie
    useEffect(() => {
        if (!selectedYear) return;

        let startDate: string | null = null;
        let endDate: string | null = null;

        switch (periodType) {
            case "month": {
                startDate = formatDateToLocalString(new Date(Number(selectedYear), Number(selectedMonth) - 1, 1));
                endDate = formatDateToLocalString(new Date(Number(selectedYear), Number(selectedMonth), 0));
                break;
            }
            case "quarter": {
                const quarterStartMonth = (Number(selectedQuarter) - 1) * 3;
                startDate = formatDateToLocalString(new Date(Number(selectedYear), quarterStartMonth, 1));
                endDate = formatDateToLocalString(new Date(Number(selectedYear), quarterStartMonth + 3, 0));
                break;
            }
            case "halfyear": {
                const halfStartMonth = selectedHalf === "1" ? 0 : 6;
                startDate = formatDateToLocalString(new Date(Number(selectedYear), halfStartMonth, 1));
                endDate = formatDateToLocalString(new Date(Number(selectedYear), halfStartMonth + 6, 0));
                break;
            }
            case "year": {
                startDate = `${selectedYear}-01-01`;
                endDate = `${selectedYear}-12-31`;
                break;
            }
        }


        if (startDate && endDate) {
            onChange(startDate, endDate);
        }
    }, [periodType, selectedYear, selectedMonth, selectedQuarter, selectedHalf]);

    return (
        <div className="flex items-center gap-2">
            <select
                value={periodType}
                onChange={(e) => setPeriodType(e.target.value as any)}
                className="text-sm px-2 py-1 rounded bg-white border border-gray-300"
            >
                <option value="month">Maand</option>
                <option value="quarter">Kwartaal</option>
                <option value="halfyear">Halfjaar</option>
                <option value="year">Jaar</option>
            </select>

            {periodType === "month" && (
                <select
                    value={selectedMonth}
                    onChange={(e) => setSelectedMonth(e.target.value)}
                    className="text-sm px-2 py-1 rounded bg-white border border-gray-300"
                >
                    {availableMonths.map((month) => (
                        <option key={month} value={month}>
                            {formatMonthFull(`${selectedYear}-${month}-01`)}
                        </option>
                    ))}
                </select>
            )}

            {periodType === "quarter" && (
                <select
                    value={selectedQuarter}
                    onChange={(e) => setSelectedQuarter(e.target.value as any)}
                    className="text-sm px-2 py-1 rounded bg-white border border-gray-300"
                >
                    {availableQuarters.map((quarter) => (
                        <option key={quarter} value={quarter}>
                            Kwartaal {quarter}
                        </option>
                    ))}
                </select>
            )}

            {periodType === "halfyear" && (
                <select
                    value={selectedHalf}
                    onChange={(e) => setSelectedHalf(e.target.value as any)}
                    className="text-sm px-2 py-1 rounded bg-white border border-gray-300"
                >
                    {availableHalves.map((half) => (
                        <option key={half} value={half}>
                            {half === "1" ? "Eerste halfjaar" : "Tweede halfjaar"}
                        </option>
                    ))}
                </select>
            )}

            <select
                value={selectedYear}
                onChange={(e) => setSelectedYear(e.target.value)}
                className="text-sm px-2 py-1 rounded bg-white border border-gray-300"
            >
                {availableYears.map((year) => (
                    <option key={year} value={year}>
                        {year}
                    </option>
                ))}
            </select>
        </div>
    );
}
