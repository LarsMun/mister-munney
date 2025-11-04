// src/domains/transactions/components/PeriodPicker.tsx
import { useEffect, useState, useRef } from "react";
import {formatDateToLocalString, formatMonthFull} from "../../../shared/utils/DateFormat";
import { ChevronLeft, ChevronRight } from "lucide-react";

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
    const previousDatesRef = useRef<{ start: string; end: string } | null>(null);
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

    // Zet automatisch nieuwste jaar & maand bij laden (alleen eerste keer)
    useEffect(() => {
        if (months.length > 0 && !selectedYear) {
            const [year, month] = months[0].split('-');
            setSelectedYear(year);
            setSelectedMonth(month);
        }
    }, [months, selectedYear]);

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
            // Only call onChange if dates actually changed
            const prev = previousDatesRef.current;
            if (!prev || prev.start !== startDate || prev.end !== endDate) {
                previousDatesRef.current = { start: startDate, end: endDate };
                onChange(startDate, endDate);
            }
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [periodType, selectedYear, selectedMonth, selectedQuarter, selectedHalf]);

    // Logica voor vorige/volgende periode navigatie
    let hasPrevious = false;
    let hasNext = false;

    if (periodType === "month") {
        const currentMonthKey = `${selectedYear}-${selectedMonth}`;
        const currentMonthIndex = months.indexOf(currentMonthKey);
        hasPrevious = currentMonthIndex < months.length - 1;
        hasNext = currentMonthIndex > 0;
    } else if (periodType === "quarter") {
        const currentQuarterNum = Number(selectedQuarter);
        const currentYearNum = Number(selectedYear);

        // Check vorige kwartaal
        if (currentQuarterNum > 1) {
            hasPrevious = true;
        } else {
            const prevYearIndex = availableYears.indexOf(selectedYear) + 1;
            hasPrevious = prevYearIndex < availableYears.length;
        }

        // Check volgende kwartaal
        if (currentQuarterNum < 4) {
            const nextQuarterStartMonth = currentQuarterNum * 3;
            const nextQuarterDate = new Date(currentYearNum, nextQuarterStartMonth);
            hasNext = lastDate ? nextQuarterDate <= lastDate : false;
        } else {
            const nextYearIndex = availableYears.indexOf(selectedYear) - 1;
            hasNext = nextYearIndex >= 0;
        }
    } else if (periodType === "halfyear") {
        const currentHalfNum = Number(selectedHalf);
        const currentYearNum = Number(selectedYear);

        // Check vorige halfjaar
        if (currentHalfNum > 1) {
            hasPrevious = true;
        } else {
            const prevYearIndex = availableYears.indexOf(selectedYear) + 1;
            hasPrevious = prevYearIndex < availableYears.length;
        }

        // Check volgende halfjaar
        if (currentHalfNum < 2) {
            const nextHalfDate = new Date(currentYearNum, 6);
            hasNext = lastDate ? nextHalfDate <= lastDate : false;
        } else {
            const nextYearIndex = availableYears.indexOf(selectedYear) - 1;
            hasNext = nextYearIndex >= 0;
        }
    } else if (periodType === "year") {
        const yearIndex = availableYears.indexOf(selectedYear);
        hasPrevious = yearIndex < availableYears.length - 1;
        hasNext = yearIndex > 0;
    }

    const goToPrevious = () => {
        if (periodType === "month") {
            const currentMonthKey = `${selectedYear}-${selectedMonth}`;
            const currentMonthIndex = months.indexOf(currentMonthKey);
            if (currentMonthIndex < months.length - 1) {
                const previousMonth = months[currentMonthIndex + 1];
                const [year, month] = previousMonth.split("-");
                setSelectedYear(year);
                setSelectedMonth(month);
            }
        } else if (periodType === "quarter") {
            const currentQuarterNum = Number(selectedQuarter);
            if (currentQuarterNum > 1) {
                setSelectedQuarter(String(currentQuarterNum - 1) as any);
            } else {
                const prevYearIndex = availableYears.indexOf(selectedYear) + 1;
                if (prevYearIndex < availableYears.length) {
                    setSelectedYear(availableYears[prevYearIndex]);
                    setSelectedQuarter("4");
                }
            }
        } else if (periodType === "halfyear") {
            const currentHalfNum = Number(selectedHalf);
            if (currentHalfNum > 1) {
                setSelectedHalf("1");
            } else {
                const prevYearIndex = availableYears.indexOf(selectedYear) + 1;
                if (prevYearIndex < availableYears.length) {
                    setSelectedYear(availableYears[prevYearIndex]);
                    setSelectedHalf("2");
                }
            }
        } else if (periodType === "year") {
            const yearIndex = availableYears.indexOf(selectedYear);
            if (yearIndex < availableYears.length - 1) {
                setSelectedYear(availableYears[yearIndex + 1]);
            }
        }
    };

    const goToNext = () => {
        if (periodType === "month") {
            const currentMonthKey = `${selectedYear}-${selectedMonth}`;
            const currentMonthIndex = months.indexOf(currentMonthKey);
            if (currentMonthIndex > 0) {
                const nextMonth = months[currentMonthIndex - 1];
                const [year, month] = nextMonth.split("-");
                setSelectedYear(year);
                setSelectedMonth(month);
            }
        } else if (periodType === "quarter") {
            const currentQuarterNum = Number(selectedQuarter);
            if (currentQuarterNum < 4) {
                setSelectedQuarter(String(currentQuarterNum + 1) as any);
            } else {
                const nextYearIndex = availableYears.indexOf(selectedYear) - 1;
                if (nextYearIndex >= 0) {
                    setSelectedYear(availableYears[nextYearIndex]);
                    setSelectedQuarter("1");
                }
            }
        } else if (periodType === "halfyear") {
            const currentHalfNum = Number(selectedHalf);
            if (currentHalfNum < 2) {
                setSelectedHalf("2");
            } else {
                const nextYearIndex = availableYears.indexOf(selectedYear) - 1;
                if (nextYearIndex >= 0) {
                    setSelectedYear(availableYears[nextYearIndex]);
                    setSelectedHalf("1");
                }
            }
        } else if (periodType === "year") {
            const yearIndex = availableYears.indexOf(selectedYear);
            if (yearIndex > 0) {
                setSelectedYear(availableYears[yearIndex - 1]);
            }
        }
    };

    return (
        <div className="flex items-center gap-2">
            {hasPrevious && (
                <button
                    onClick={goToPrevious}
                    className="p-1 rounded hover:bg-gray-100 border border-gray-300 bg-white"
                    title={periodType === "month" ? "Vorige maand" : periodType === "quarter" ? "Vorig kwartaal" : periodType === "halfyear" ? "Vorig halfjaar" : "Vorig jaar"}
                >
                    <ChevronLeft className="w-5 h-5" />
                </button>
            )}

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

            {hasNext && (
                <button
                    onClick={goToNext}
                    className="p-1 rounded hover:bg-gray-100 border border-gray-300 bg-white"
                    title={periodType === "month" ? "Volgende maand" : periodType === "quarter" ? "Volgend kwartaal" : periodType === "halfyear" ? "Volgend halfjaar" : "Volgend jaar"}
                >
                    <ChevronRight className="w-5 h-5" />
                </button>
            )}
        </div>
    );
}
