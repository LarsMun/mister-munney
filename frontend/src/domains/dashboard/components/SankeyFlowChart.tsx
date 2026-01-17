import { useState, useEffect, useMemo } from 'react';
import { fetchSankeyFlow } from '../../budgets/services/AdaptiveDashboardService';
import type { SankeyFlowData, SankeyMode } from '../models/SankeyFlow';
import { formatMoney } from '../../../shared/utils/MoneyFormat';

interface SankeyFlowChartProps {
    accountId: number;
    startDate: string;
    endDate: string;
}

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

export default function SankeyFlowChart({ accountId, startDate, endDate }: SankeyFlowChartProps) {
    const [mode, setMode] = useState<SankeyMode>('actual');
    const [data, setData] = useState<SankeyFlowData | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [tooltip, setTooltip] = useState<{ x: number; y: number; content: string } | null>(null);

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

        // Extract budget nodes (no categories)
        const incomeBudgets: { name: string; value: number }[] = [];
        const expenseBudgets: { name: string; value: number }[] = [];

        // Build budget totals from links
        const budgetTotals = new Map<number, number>();

        data.links.forEach(link => {
            const sourceNode = data.nodes[link.source];
            const targetNode = data.nodes[link.target];

            // Income budget -> category links: aggregate by budget
            if (sourceNode.type === 'income_budget') {
                budgetTotals.set(link.source, (budgetTotals.get(link.source) || 0) + link.value);
            }
            // Total -> expense budget links
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

        // Sort by value descending
        incomeBudgets.sort((a, b) => b.value - a.value);
        expenseBudgets.sort((a, b) => b.value - a.value);

        const totalIncome = incomeBudgets.reduce((s, b) => s + b.value, 0);
        const totalExpense = expenseBudgets.reduce((s, b) => s + b.value, 0);

        if (totalIncome === 0 && totalExpense === 0) return null;

        // Layout dimensions
        const width = 900;
        const height = 500;
        const padding = { top: 40, bottom: 40, left: 20, right: 20 };
        const nodeWidth = 20;
        const nodePadding = 12;
        const columnX = [padding.left, width / 2 - nodeWidth / 2, width - padding.right - nodeWidth];
        const innerHeight = height - padding.top - padding.bottom;

        // Calculate node heights proportionally
        const maxValue = Math.max(totalIncome, totalExpense);
        const scale = innerHeight / maxValue;

        // Position income nodes (left column)
        const incomeNodes: NodePosition[] = [];
        let incomeY = padding.top;
        const incomeSpacing = incomeBudgets.length > 1
            ? (innerHeight - incomeBudgets.reduce((s, b) => s + b.value * scale, 0)) / (incomeBudgets.length - 1)
            : 0;

        incomeBudgets.forEach(b => {
            const h = Math.max(b.value * scale, 4);
            incomeNodes.push({ name: b.name, type: 'income', value: b.value, y: incomeY, height: h, color: COLORS.income });
            incomeY += h + Math.min(incomeSpacing, nodePadding);
        });

        // Position total node (center)
        const totalHeight = Math.max(totalIncome, totalExpense) * scale;
        const totalY = padding.top + (innerHeight - totalHeight) / 2;
        const totalNode: NodePosition = { name: 'Totaal', type: 'total', value: Math.max(totalIncome, totalExpense), y: totalY, height: totalHeight, color: COLORS.total };

        // Position expense nodes (right column)
        const expenseNodes: NodePosition[] = [];
        let expenseY = padding.top;
        const expenseSpacing = expenseBudgets.length > 1
            ? (innerHeight - expenseBudgets.reduce((s, b) => s + b.value * scale, 0)) / (expenseBudgets.length - 1)
            : 0;

        expenseBudgets.forEach(b => {
            const h = Math.max(b.value * scale, 4);
            expenseNodes.push({ name: b.name, type: 'expense', value: b.value, y: expenseY, height: h, color: COLORS.expense });
            expenseY += h + Math.min(expenseSpacing, nodePadding);
        });

        // Build flow paths
        const flows: FlowPath[] = [];

        // Income → Total flows
        let incomeFlowY = totalY;
        incomeNodes.forEach(node => {
            const flowHeight = node.height;
            flows.push({
                sourceY: node.y,
                sourceHeight: node.height,
                targetY: incomeFlowY,
                targetHeight: flowHeight,
                value: node.value,
                color: COLORS.income,
                sourceName: node.name,
                targetName: 'Totaal',
            });
            incomeFlowY += flowHeight;
        });

        // Total → Expense flows
        let expenseFlowY = totalY;
        expenseNodes.forEach(node => {
            const flowHeight = node.height;
            flows.push({
                sourceY: expenseFlowY,
                sourceHeight: flowHeight,
                targetY: node.y,
                targetHeight: node.height,
                value: node.value,
                color: COLORS.expense,
                sourceName: 'Totaal',
                targetName: node.name,
            });
            expenseFlowY += flowHeight;
        });

        return {
            width,
            height,
            nodeWidth,
            columnX,
            incomeNodes,
            totalNode,
            expenseNodes,
            flows,
            totalIncome,
            totalExpense,
        };
    }, [data]);

    // Generate curved path for flow
    const flowPath = (flow: FlowPath, sourceX: number, targetX: number, nodeWidth: number) => {
        const x0 = sourceX + nodeWidth;
        const x1 = targetX;
        const xi = (x0 + x1) / 2;

        const y0Top = flow.sourceY;
        const y0Bot = flow.sourceY + flow.sourceHeight;
        const y1Top = flow.targetY;
        const y1Bot = flow.targetY + flow.targetHeight;

        return `
            M ${x0} ${y0Top}
            C ${xi} ${y0Top}, ${xi} ${y1Top}, ${x1} ${y1Top}
            L ${x1} ${y1Bot}
            C ${xi} ${y1Bot}, ${xi} ${y0Bot}, ${x0} ${y0Bot}
            Z
        `;
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-[500px]">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600" />
            </div>
        );
    }

    if (error) {
        return <div className="flex items-center justify-center h-[500px] text-red-500">{error}</div>;
    }

    if (!layout) {
        return (
            <div className="flex items-center justify-center h-[500px] text-gray-500">
                <div className="text-center">
                    <p className="text-lg font-medium">Geen flow data beschikbaar</p>
                    <p className="text-sm mt-2">Er zijn geen transacties in budgetten voor deze periode</p>
                </div>
            </div>
        );
    }

    return (
        <div className="w-full">
            {/* Controls */}
            <div className="flex flex-wrap justify-between items-center gap-4 mb-4">
                <div className="flex gap-2">
                    <button
                        onClick={() => setMode('actual')}
                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                            mode === 'actual' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        Actueel
                    </button>
                    <button
                        onClick={() => setMode('median')}
                        className={`px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                            mode === 'median' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                        }`}
                    >
                        Mediaan (6 mnd)
                    </button>
                </div>
                <div className="flex flex-wrap gap-6 text-sm">
                    <span className="flex items-center gap-2">
                        <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.income }} />
                        <span className="text-gray-600">Inkomsten:</span>
                        <span className="font-semibold" style={{ color: COLORS.income }}>{formatMoney(layout.totalIncome)}</span>
                    </span>
                    <span className="flex items-center gap-2">
                        <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.expense }} />
                        <span className="text-gray-600">Uitgaven:</span>
                        <span className="font-semibold" style={{ color: COLORS.expense }}>{formatMoney(layout.totalExpense)}</span>
                    </span>
                    <span className="flex items-center gap-2">
                        <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.total }} />
                        <span className="text-gray-600">Netto:</span>
                        <span className="font-bold" style={{ color: layout.totalIncome - layout.totalExpense >= 0 ? COLORS.income : COLORS.expense }}>
                            {formatMoney(layout.totalIncome - layout.totalExpense)}
                        </span>
                    </span>
                </div>
            </div>

            {/* Chart */}
            <div className="w-full bg-white border border-gray-200 rounded-lg">
                <svg
                    width="100%"
                    height="500"
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
                                    style={{ cursor: 'pointer' }}
                                />
                            );
                        })}
                    </g>

                    {/* Income nodes (left) */}
                    <g>
                        {layout.incomeNodes.map((node, i) => (
                            <g key={`income-${i}`}>
                                <rect
                                    x={layout.columnX[0]}
                                    y={node.y}
                                    width={layout.nodeWidth}
                                    height={node.height}
                                    fill={node.color}
                                    rx={2}
                                />
                                {/* Label left of node */}
                                <text
                                    x={layout.columnX[0] - 10}
                                    y={node.y + node.height / 2}
                                    textAnchor="end"
                                    dominantBaseline="middle"
                                    fontSize={13}
                                    fontWeight={500}
                                    fill="#374151"
                                >
                                    {node.name}
                                </text>
                                <text
                                    x={layout.columnX[0] - 10}
                                    y={node.y + node.height / 2 + 16}
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

                    {/* Total node (center) */}
                    <g>
                        <rect
                            x={layout.columnX[1]}
                            y={layout.totalNode.y}
                            width={layout.nodeWidth}
                            height={layout.totalNode.height}
                            fill={layout.totalNode.color}
                            rx={2}
                        />
                        <text
                            x={layout.columnX[1] + layout.nodeWidth / 2}
                            y={layout.totalNode.y + layout.totalNode.height / 2 - 10}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            fontSize={15}
                            fontWeight={600}
                            fill="#374151"
                        >
                            Totaal
                        </text>
                        <text
                            x={layout.columnX[1] + layout.nodeWidth / 2}
                            y={layout.totalNode.y + layout.totalNode.height / 2 + 10}
                            textAnchor="middle"
                            dominantBaseline="middle"
                            fontSize={13}
                            fill="#6b7280"
                        >
                            {formatMoney(Math.max(layout.totalIncome, layout.totalExpense))}
                        </text>
                    </g>

                    {/* Expense nodes (right) */}
                    <g>
                        {layout.expenseNodes.map((node, i) => (
                            <g key={`expense-${i}`}>
                                <rect
                                    x={layout.columnX[2]}
                                    y={node.y}
                                    width={layout.nodeWidth}
                                    height={node.height}
                                    fill={node.color}
                                    rx={2}
                                />
                                {/* Label right of node */}
                                <text
                                    x={layout.columnX[2] + layout.nodeWidth + 10}
                                    y={node.y + node.height / 2}
                                    textAnchor="start"
                                    dominantBaseline="middle"
                                    fontSize={13}
                                    fontWeight={500}
                                    fill="#374151"
                                >
                                    {node.name}
                                </text>
                                <text
                                    x={layout.columnX[2] + layout.nodeWidth + 10}
                                    y={node.y + node.height / 2 + 16}
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
            </div>

            {/* Tooltip */}
            {tooltip && (
                <div
                    className="fixed bg-white px-3 py-2 border border-gray-200 rounded-lg shadow-lg text-sm z-50 pointer-events-none"
                    style={{ left: tooltip.x + 12, top: tooltip.y - 12 }}
                >
                    {tooltip.content}
                </div>
            )}
        </div>
    );
}
