import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { X } from 'lucide-react';
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
    deficit: '#f97316',   // Orange for deficit (Tekort)
    surplus: '#22c55e',   // Green for surplus (Overschot)
};

export default function SankeyFlowPage() {
    const navigate = useNavigate();
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

    // Close on Escape key
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') navigate('/');
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [navigate]);

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

    // Build layout - dynamic height based on content
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

        // Balance the flows: Tekort/Overschot always on expense side (bottom)
        const difference = totalIncome - totalExpense;
        if (difference < 0) {
            // Expense > Income: Tekort (deficit) on expense side, balance income with hidden source
            expenseBudgets.push({ name: 'Tekort', value: Math.abs(difference) });
            incomeBudgets.push({ name: 'Spaargeld opname', value: Math.abs(difference) });
        } else if (difference > 0) {
            // Income > Expense: Overschot (surplus) on expense side
            expenseBudgets.push({ name: 'Overschot', value: difference });
        }

        // Now both sides are balanced
        const balancedTotal = Math.max(totalIncome, totalExpense) + (difference < 0 ? Math.abs(difference) : 0);

        // Wide, short layout that fits any viewport
        const width = 1400;
        const height = 500;
        const padding = { top: 30, bottom: 30, left: 180, right: 180 };
        const nodeWidth = 24;
        const innerHeight = height - padding.top - padding.bottom;

        const columnX = [padding.left, width / 2 - nodeWidth / 2, width - padding.right - nodeWidth];

        // Calculate node heights - use equal distribution based on count
        const maxNodes = Math.max(incomeBudgets.length, expenseBudgets.length, 1);
        const nodeGap = 12;
        const availableForNodes = innerHeight - (maxNodes - 1) * nodeGap;
        const nodeHeight = Math.max(20, availableForNodes / maxNodes);

        // Position income nodes evenly (with special color for Tekort)
        const incomeNodes: NodePosition[] = [];
        const totalIncomeNodesHeight = incomeBudgets.length * nodeHeight + (incomeBudgets.length - 1) * nodeGap;
        let incomeY = padding.top + (innerHeight - totalIncomeNodesHeight) / 2;

        incomeBudgets.forEach(b => {
            const color = b.name === 'Spaargeld opname' ? COLORS.deficit : COLORS.income;
            incomeNodes.push({ name: b.name, type: 'income', value: b.value, y: incomeY, height: nodeHeight, color });
            incomeY += nodeHeight + nodeGap;
        });

        // Position expense nodes evenly (with special color for Overschot)
        const expenseNodes: NodePosition[] = [];
        const totalExpenseNodesHeight = expenseBudgets.length * nodeHeight + (expenseBudgets.length - 1) * nodeGap;
        let expenseY = padding.top + (innerHeight - totalExpenseNodesHeight) / 2;

        expenseBudgets.forEach(b => {
            let color = COLORS.expense;
            if (b.name === 'Overschot') color = COLORS.surplus;
            if (b.name === 'Tekort') color = COLORS.deficit;
            expenseNodes.push({ name: b.name, type: 'expense', value: b.value, y: expenseY, height: nodeHeight, color });
            expenseY += nodeHeight + nodeGap;
        });

        // Total node spans the full inner height to connect both sides
        const totalNode: NodePosition = {
            name: 'Totaal',
            type: 'total',
            value: balancedTotal,
            y: padding.top,
            height: innerHeight,
            color: COLORS.total
        };

        // Build flows with proportional heights based on values
        const flows: FlowPath[] = [];

        // Income flows - proportional to their values (using balanced total)
        let incomeFlowY = padding.top;
        const flowScale = innerHeight / balancedTotal;
        incomeNodes.forEach(node => {
            const flowHeightOnTotal = node.value * flowScale;
            flows.push({
                sourceY: node.y,
                sourceHeight: nodeHeight,
                targetY: incomeFlowY,
                targetHeight: flowHeightOnTotal,
                value: node.value,
                color: node.color,
                sourceName: node.name,
                targetName: 'Totaal',
            });
            incomeFlowY += flowHeightOnTotal;
        });

        // Expense flows - proportional to their values (using same balanced scale)
        let expenseFlowY = padding.top;
        expenseNodes.forEach(node => {
            const flowHeightOnTotal = node.value * flowScale;
            flows.push({
                sourceY: expenseFlowY,
                sourceHeight: flowHeightOnTotal,
                targetY: node.y,
                targetHeight: nodeHeight,
                value: node.value,
                color: node.color,
                sourceName: 'Totaal',
                targetName: node.name,
            });
            expenseFlowY += flowHeightOnTotal;
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

    const formatMonth = (month: string) => {
        const [year, m] = month.split('-');
        const monthNames = ['Januari', 'Februari', 'Maart', 'April', 'Mei', 'Juni', 'Juli', 'Augustus', 'September', 'Oktober', 'November', 'December'];
        return `${monthNames[Number(m) - 1]} ${year}`;
    };

    return (
        <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center">
            {/* Modal container */}
            <div className="bg-white w-[95vw] h-[90vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden">
                {/* Header */}
                <div className="flex-shrink-0 flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div className="flex items-center gap-6">
                        <h1 className="text-xl font-bold text-gray-800">Geldstroom Diagram</h1>

                        {/* Month selector */}
                        <select
                            value={selectedMonth}
                            onChange={(e) => handleMonthChange(e.target.value)}
                            className="px-4 py-2 rounded-lg border border-gray-300 bg-white font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            {months.map(month => (
                                <option key={month} value={month}>{formatMonth(month)}</option>
                            ))}
                        </select>

                        {/* Mode toggle */}
                        <div className="flex gap-1 bg-gray-200 p-1 rounded-lg">
                            <button
                                onClick={() => setMode('actual')}
                                className={`px-4 py-1.5 rounded-md text-sm font-medium transition-all ${
                                    mode === 'actual' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                Actueel
                            </button>
                            <button
                                onClick={() => setMode('median')}
                                className={`px-4 py-1.5 rounded-md text-sm font-medium transition-all ${
                                    mode === 'median' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'
                                }`}
                            >
                                Mediaan (6 mnd)
                            </button>
                        </div>
                    </div>

                    <div className="flex items-center gap-6">
                        {layout && (
                            <div className="flex gap-6 text-sm">
                                <span className="flex items-center gap-2">
                                    <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.income }} />
                                    <span className="text-gray-500">Inkomsten:</span>
                                    <span className="font-bold" style={{ color: COLORS.income }}>{formatMoney(layout.totalIncome)}</span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.expense }} />
                                    <span className="text-gray-500">Uitgaven:</span>
                                    <span className="font-bold" style={{ color: COLORS.expense }}>{formatMoney(layout.totalExpense)}</span>
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.total }} />
                                    <span className="text-gray-500">Netto:</span>
                                    <span className="font-bold" style={{ color: layout.totalIncome - layout.totalExpense >= 0 ? COLORS.income : COLORS.expense }}>
                                        {formatMoney(layout.totalIncome - layout.totalExpense)}
                                    </span>
                                </span>
                            </div>
                        )}

                        <button
                            onClick={() => navigate('/')}
                            className="p-2 rounded-lg hover:bg-gray-200 transition-colors"
                            title="Sluiten (Esc)"
                        >
                            <X className="w-6 h-6 text-gray-500" />
                        </button>
                    </div>
                </div>

                {/* Content */}
                <div className="flex-1 flex items-center justify-center p-4 min-h-0">
                    {isLoading ? (
                        <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600" />
                    ) : error ? (
                        <div className="text-red-500 text-xl">{error}</div>
                    ) : !layout ? (
                        <div className="text-center text-gray-500">
                            <p className="text-2xl font-medium">Geen flow data beschikbaar</p>
                            <p className="text-lg mt-2">Er zijn geen transacties in budgetten voor deze periode</p>
                        </div>
                    ) : (
                        <svg
                            viewBox={`0 0 ${layout.width} ${layout.height}`}
                            preserveAspectRatio="xMidYMid meet"
                            style={{ width: '100%', height: '100%', maxHeight: 'calc(90vh - 120px)' }}
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
                                            x={layout.columnX[0] - 12}
                                            y={node.y + node.height / 2}
                                            textAnchor="end"
                                            dominantBaseline="middle"
                                            fontSize={14}
                                            fontWeight={600}
                                            fill="#374151"
                                        >
                                            {node.name}
                                        </text>
                                        <text
                                            x={layout.columnX[0] - 12}
                                            y={node.y + node.height / 2 + 18}
                                            textAnchor="end"
                                            dominantBaseline="middle"
                                            fontSize={12}
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
                                    y={layout.totalNode.y + layout.totalNode.height / 2 - 12}
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    fontSize={18}
                                    fontWeight={700}
                                    fill="#374151"
                                >
                                    Totaal
                                </text>
                                <text
                                    x={layout.columnX[1] + layout.nodeWidth / 2}
                                    y={layout.totalNode.y + layout.totalNode.height / 2 + 12}
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    fontSize={14}
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
                                            x={layout.columnX[2] + layout.nodeWidth + 12}
                                            y={node.y + node.height / 2}
                                            textAnchor="start"
                                            dominantBaseline="middle"
                                            fontSize={14}
                                            fontWeight={600}
                                            fill="#374151"
                                        >
                                            {node.name}
                                        </text>
                                        <text
                                            x={layout.columnX[2] + layout.nodeWidth + 12}
                                            y={node.y + node.height / 2 + 18}
                                            textAnchor="start"
                                            dominantBaseline="middle"
                                            fontSize={12}
                                            fill="#6b7280"
                                        >
                                            {formatMoney(node.value)}
                                        </text>
                                    </g>
                                ))}
                            </g>
                        </svg>
                    )}
                </div>
            </div>

            {/* Tooltip */}
            {tooltip && (
                <div
                    className="fixed bg-gray-900 text-white px-4 py-2 rounded-lg shadow-xl text-sm z-[60] pointer-events-none"
                    style={{ left: tooltip.x + 15, top: tooltip.y - 15 }}
                >
                    {tooltip.content}
                </div>
            )}
        </div>
    );
}
