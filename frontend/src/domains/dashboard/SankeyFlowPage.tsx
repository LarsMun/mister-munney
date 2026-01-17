import { useState, useEffect, useMemo } from 'react';
import { Link } from 'react-router-dom';
import { ArrowLeft } from 'lucide-react';
import { useAccount } from '../../app/context/AccountContext';
import { fetchSankeyFlow } from '../budgets/services/AdaptiveDashboardService';
import { getAvailableMonths } from '../transactions/services/TransactionsService';
import type { SankeyFlowData, SankeyMode } from './models/SankeyFlow';
import { formatMoney } from '../../shared/utils/MoneyFormat';
import { formatDateToLocalString } from '../../shared/utils/DateFormat';

interface NodePosition {
    name: string;
    type: string;
    value: number;
    y: number;
    height: number;
    color: string;
}

interface FlowPath {
    sourceY: number;
    sourceHeight: number;
    targetY: number;
    targetHeight: number;
    value: number;
    color: string;
    sourceName: string;
    targetName: string;
}

const COLORS = {
    income: '#16a34a',
    total: '#3b82f6',
    expense: '#dc2626',
};

export default function SankeyFlowPage() {
    const { accountId } = useAccount();
    const [mode, setMode] = useState<SankeyMode>('actual');
    const [data, setData] = useState<SankeyFlowData | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<{ x: number; y: number; content: string } | null>(null);
    const [startDate, setStartDate] = useState<string | null>(null);
    const [endDate, setEndDate] = useState<string | null>(null);
    const [months, setMonths] = useState<string[]>([]);
    const [selectedMonth, setSelectedMonth] = useState<string>('');

    // Load available months
    useEffect(() => {
        if (!accountId) return;

        getAvailableMonths(accountId)
            .then(availableMonths => {
                setMonths(availableMonths);
                if (availableMonths.length > 0) {
                    const currentMonth = availableMonths[0];
                    setSelectedMonth(currentMonth);
                    const [year, month] = currentMonth.split("-");
                    const start = formatDateToLocalString(new Date(Number(year), Number(month) - 1, 1));
                    const end = formatDateToLocalString(new Date(Number(year), Number(month), 0));
                    setStartDate(start);
                    setEndDate(end);
                }
            })
            .catch(err => {
                console.error('Error loading months:', err);
                setError('Kon periodes niet laden');
                setIsLoading(false);
            });
    }, [accountId]);

    // Handle month change
    const handleMonthChange = (month: string) => {
        setSelectedMonth(month);
        const [year, m] = month.split("-");
        const start = formatDateToLocalString(new Date(Number(year), Number(m) - 1, 1));
        const end = formatDateToLocalString(new Date(Number(year), Number(m), 0));
        setStartDate(start);
        setEndDate(end);
    };

    // Load sankey data
    useEffect(() => {
        async function loadData() {
            if (!accountId || !startDate || !endDate) return;
            setIsLoading(true);
            setError(null);
            try {
                const flowData = await fetchSankeyFlow(accountId, startDate, endDate, mode);
                setData(flowData);
            } catch (err) {
                console.error('Error fetching Sankey data:', err);
                setError('Kon flow data niet laden');
            } finally {
                setIsLoading(false);
            }
        }
        loadData();
    }, [accountId, startDate, endDate, mode]);

    // Build simple 3-column layout: Income Budgets → Total → Expense Budgets
    const layout = useMemo(() => {
        if (!data) return null;

        const incomeBudgets: { name: string; value: number }[] = [];
        const expenseBudgets: { name: string; value: number }[] = [];
        const budgetTotals = new Map<number, number>();

        data.links.forEach(link => {
            const sourceNode = data.nodes[link.source];
            const targetNode = data.nodes[link.target];

            if (sourceNode.type === 'income_budget') {
                budgetTotals.set(link.source, (budgetTotals.get(link.source) || 0) + link.value);
            }
            if (sourceNode.type === 'total' && targetNode.type === 'expense_budget') {
                budgetTotals.set(link.target, (budgetTotals.get(link.target) || 0) + link.value);
            }
        });

        data.nodes.forEach((node, idx) => {
            const value = budgetTotals.get(idx) || 0;
            if (value <= 0) return;

            if (node.type === 'income_budget') {
                incomeBudgets.push({ name: node.name, value });
            } else if (node.type === 'expense_budget') {
                expenseBudgets.push({ name: node.name, value });
            }
        });

        incomeBudgets.sort((a, b) => b.value - a.value);
        expenseBudgets.sort((a, b) => b.value - a.value);

        const totalIncome = incomeBudgets.reduce((s, b) => s + b.value, 0);
        const totalExpense = expenseBudgets.reduce((s, b) => s + b.value, 0);

        if (totalIncome === 0 && totalExpense === 0) return null;

        // Large layout for full page
        const width = 1800;
        const height = 900;
        const padding = { top: 60, bottom: 60, left: 40, right: 40 };
        const nodeWidth = 28;
        const nodePadding = 20;
        const columnX = [padding.left, width / 2 - nodeWidth / 2, width - padding.right - nodeWidth];
        const innerHeight = height - padding.top - padding.bottom;

        const maxValue = Math.max(totalIncome, totalExpense);
        const scale = innerHeight / maxValue;

        // Position income nodes
        const incomeNodes: NodePosition[] = [];
        let incomeY = padding.top;
        const totalIncomeHeight = incomeBudgets.reduce((s, b) => s + Math.max(b.value * scale, 8), 0);
        const incomeSpacing = incomeBudgets.length > 1
            ? (innerHeight - totalIncomeHeight) / (incomeBudgets.length - 1)
            : 0;

        incomeBudgets.forEach(b => {
            const h = Math.max(b.value * scale, 8);
            incomeNodes.push({ name: b.name, type: 'income', value: b.value, y: incomeY, height: h, color: COLORS.income });
            incomeY += h + Math.min(incomeSpacing, nodePadding);
        });

        // Position total node
        const totalHeight = Math.max(totalIncome, totalExpense) * scale;
        const totalY = padding.top + (innerHeight - totalHeight) / 2;
        const totalNode: NodePosition = { name: 'Totaal', type: 'total', value: Math.max(totalIncome, totalExpense), y: totalY, height: totalHeight, color: COLORS.total };

        // Position expense nodes
        const expenseNodes: NodePosition[] = [];
        let expenseY = padding.top;
        const totalExpenseHeight = expenseBudgets.reduce((s, b) => s + Math.max(b.value * scale, 8), 0);
        const expenseSpacing = expenseBudgets.length > 1
            ? (innerHeight - totalExpenseHeight) / (expenseBudgets.length - 1)
            : 0;

        expenseBudgets.forEach(b => {
            const h = Math.max(b.value * scale, 8);
            expenseNodes.push({ name: b.name, type: 'expense', value: b.value, y: expenseY, height: h, color: COLORS.expense });
            expenseY += h + Math.min(expenseSpacing, nodePadding);
        });

        // Build flows
        const flows: FlowPath[] = [];

        let incomeFlowY = totalY;
        incomeNodes.forEach(node => {
            flows.push({
                sourceY: node.y,
                sourceHeight: node.height,
                targetY: incomeFlowY,
                targetHeight: node.height,
                value: node.value,
                color: COLORS.income,
                sourceName: node.name,
                targetName: 'Totaal',
            });
            incomeFlowY += node.height;
        });

        let expenseFlowY = totalY;
        expenseNodes.forEach(node => {
            flows.push({
                sourceY: expenseFlowY,
                sourceHeight: node.height,
                targetY: node.y,
                targetHeight: node.height,
                value: node.value,
                color: COLORS.expense,
                sourceName: 'Totaal',
                targetName: node.name,
            });
            expenseFlowY += node.height;
        });

        return { width, height, nodeWidth, columnX, incomeNodes, totalNode, expenseNodes, flows, totalIncome, totalExpense };
    }, [data]);

    const flowPath = (flow: FlowPath, sourceX: number, targetX: number, nodeWidth: number) => {
        const x0 = sourceX + nodeWidth;
        const x1 = targetX;
        const xi = (x0 + x1) / 2;

        return `
            M ${x0} ${flow.sourceY}
            C ${xi} ${flow.sourceY}, ${xi} ${flow.targetY}, ${x1} ${flow.targetY}
            L ${x1} ${flow.targetY + flow.targetHeight}
            C ${xi} ${flow.targetY + flow.targetHeight}, ${xi} ${flow.sourceY + flow.sourceHeight}, ${x0} ${flow.sourceY + flow.sourceHeight}
            Z
        `;
    };

    if (isLoading) {
        return (
            <div className="min-h-screen flex items-center justify-center">
                <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen flex items-center justify-center text-red-500 text-xl">
                {error}
            </div>
        );
    }

    if (!layout) {
        return (
            <div className="min-h-screen flex items-center justify-center text-gray-500">
                <div className="text-center">
                    <p className="text-2xl font-medium">Geen flow data beschikbaar</p>
                    <p className="text-lg mt-2">Er zijn geen transacties in budgetten voor deze periode</p>
                    <Link to="/" className="mt-6 inline-flex items-center gap-2 text-blue-600 hover:underline">
                        <ArrowLeft className="w-5 h-5" />
                        Terug naar Dashboard
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
                <div className="max-w-[1920px] mx-auto px-6 py-4">
                    <div className="flex flex-wrap justify-between items-center gap-4">
                        <div className="flex items-center gap-4">
                            <Link to="/" className="text-gray-500 hover:text-gray-700 transition-colors">
                                <ArrowLeft className="w-6 h-6" />
                            </Link>
                            <h1 className="text-2xl font-bold text-gray-800">Geldstroom Diagram</h1>
                        </div>

                        <div className="flex flex-wrap items-center gap-4">
                            {/* Month selector */}
                            <select
                                value={selectedMonth}
                                onChange={(e) => handleMonthChange(e.target.value)}
                                className="px-4 py-2.5 rounded-lg border border-gray-300 bg-white font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                {months.map(month => {
                                    const [year, m] = month.split('-');
                                    const monthNames = ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'];
                                    return (
                                        <option key={month} value={month}>
                                            {monthNames[Number(m) - 1]} {year}
                                        </option>
                                    );
                                })}
                            </select>

                            <div className="flex gap-2">
                                <button
                                    onClick={() => setMode('actual')}
                                    className={`px-5 py-2.5 rounded-lg font-medium transition-all ${
                                        mode === 'actual' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    }`}
                                >
                                    Actueel
                                </button>
                                <button
                                    onClick={() => setMode('median')}
                                    className={`px-5 py-2.5 rounded-lg font-medium transition-all ${
                                        mode === 'median' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                    }`}
                                >
                                    Mediaan (6 mnd)
                                </button>
                            </div>

                            <div className="flex flex-wrap gap-6 text-base">
                                <span className="flex items-center gap-2">
                                    <span className="w-4 h-4 rounded-full" style={{ backgroundColor: COLORS.income }} />
                                    <span className="text-gray-600">Inkomsten:</span>
                                    <span className="font-bold text-lg" style={{ color: COLORS.income }}>{formatMoney(layout.totalIncome)}</span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="w-4 h-4 rounded-full" style={{ backgroundColor: COLORS.expense }} />
                                    <span className="text-gray-600">Uitgaven:</span>
                                    <span className="font-bold text-lg" style={{ color: COLORS.expense }}>{formatMoney(layout.totalExpense)}</span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="w-4 h-4 rounded-full" style={{ backgroundColor: COLORS.total }} />
                                    <span className="text-gray-600">Netto:</span>
                                    <span className="font-bold text-xl" style={{ color: layout.totalIncome - layout.totalExpense >= 0 ? COLORS.income : COLORS.expense }}>
                                        {formatMoney(layout.totalIncome - layout.totalExpense)}
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Chart - Full width, min 1800px */}
            <div className="overflow-x-auto">
                <div className="min-w-[1800px] p-6">
                    <div className="bg-white border border-gray-200 rounded-xl shadow-sm">
                        <svg
                            width="100%"
                            height="900"
                            viewBox={`0 0 ${layout.width} ${layout.height}`}
                            preserveAspectRatio="xMidYMid meet"
                            className="block"
                        >
                            {/* Flows */}
                            <g>
                                {layout.flows.map((flow, i) => {
                                    const isIncome = flow.sourceName !== 'Totaal';
                                    const sourceX = isIncome ? layout.columnX[0] : layout.columnX[1];
                                    const targetX = isIncome ? layout.columnX[1] : layout.columnX[2];

                                    return (
                                        <path
                                            key={i}
                                            d={flowPath(flow, sourceX, targetX, layout.nodeWidth)}
                                            fill={flow.color}
                                            fillOpacity={0.4}
                                            onMouseEnter={e => setTooltip({
                                                x: e.clientX,
                                                y: e.clientY,
                                                content: `${flow.sourceName} → ${flow.targetName}: ${formatMoney(flow.value)}`
                                            })}
                                            onMouseMove={e => setTooltip(t => t ? { ...t, x: e.clientX, y: e.clientY } : null)}
                                            onMouseLeave={() => setTooltip(null)}
                                            className="cursor-pointer hover:fill-opacity-60 transition-all"
                                        />
                                    );
                                })}
                            </g>

                            {/* Income nodes */}
                            <g>
                                {layout.incomeNodes.map((node, i) => (
                                    <g key={`income-${i}`}>
                                        <rect
                                            x={layout.columnX[0]}
                                            y={node.y}
                                            width={layout.nodeWidth}
                                            height={node.height}
                                            fill={node.color}
                                            rx={4}
                                        />
                                        <text
                                            x={layout.columnX[0] - 15}
                                            y={node.y + node.height / 2 - 2}
                                            textAnchor="end"
                                            dominantBaseline="middle"
                                            fontSize={16}
                                            fontWeight={600}
                                            fill="#374151"
                                        >
                                            {node.name}
                                        </text>
                                        <text
                                            x={layout.columnX[0] - 15}
                                            y={node.y + node.height / 2 + 20}
                                            textAnchor="end"
                                            dominantBaseline="middle"
                                            fontSize={14}
                                            fill="#6b7280"
                                        >
                                            {formatMoney(node.value)}
                                        </text>
                                    </g>
                                ))}
                            </g>

                            {/* Total node */}
                            <g>
                                <rect
                                    x={layout.columnX[1]}
                                    y={layout.totalNode.y}
                                    width={layout.nodeWidth}
                                    height={layout.totalNode.height}
                                    fill={layout.totalNode.color}
                                    rx={4}
                                />
                                <text
                                    x={layout.columnX[1] + layout.nodeWidth / 2}
                                    y={layout.totalNode.y + layout.totalNode.height / 2 - 14}
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    fontSize={20}
                                    fontWeight={700}
                                    fill="#374151"
                                >
                                    Totaal
                                </text>
                                <text
                                    x={layout.columnX[1] + layout.nodeWidth / 2}
                                    y={layout.totalNode.y + layout.totalNode.height / 2 + 14}
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    fontSize={16}
                                    fill="#6b7280"
                                >
                                    {formatMoney(Math.max(layout.totalIncome, layout.totalExpense))}
                                </text>
                            </g>

                            {/* Expense nodes */}
                            <g>
                                {layout.expenseNodes.map((node, i) => (
                                    <g key={`expense-${i}`}>
                                        <rect
                                            x={layout.columnX[2]}
                                            y={node.y}
                                            width={layout.nodeWidth}
                                            height={node.height}
                                            fill={node.color}
                                            rx={4}
                                        />
                                        <text
                                            x={layout.columnX[2] + layout.nodeWidth + 15}
                                            y={node.y + node.height / 2 - 2}
                                            textAnchor="start"
                                            dominantBaseline="middle"
                                            fontSize={16}
                                            fontWeight={600}
                                            fill="#374151"
                                        >
                                            {node.name}
                                        </text>
                                        <text
                                            x={layout.columnX[2] + layout.nodeWidth + 15}
                                            y={node.y + node.height / 2 + 20}
                                            textAnchor="start"
                                            dominantBaseline="middle"
                                            fontSize={14}
                                            fill="#6b7280"
                                        >
                                            {formatMoney(node.value)}
                                        </text>
                                    </g>
                                ))}
                            </g>
                        </svg>
                    </div>
                </div>
            </div>

            {/* Tooltip */}
            {tooltip && (
                <div
                    className="fixed bg-white px-4 py-3 border border-gray-200 rounded-lg shadow-xl text-base z-50 pointer-events-none"
                    style={{ left: tooltip.x + 15, top: tooltip.y - 15 }}
                >
                    {tooltip.content}
                </div>
            )}
        </div>
    );
}
