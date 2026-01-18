import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { X } from 'lucide-react';
import { useAccount } from '../../app/context/AccountContext';
import { fetchSankeyFlow } from '../budgets/services/AdaptiveDashboardService';
import { getAvailableMonths } from '../transactions/services/TransactionsService';
import type { SankeyFlowData, SankeyMode } from './models/SankeyFlow';
import { formatMoney } from '../../shared/utils/MoneyFormat';
import PeriodPicker from '../transactions/components/PeriodPicker';

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
    deficit: '#f97316',   // Orange for Tekort
    surplus: '#22c55e',   // Green for Overschot
};

// Fallback colors for categories that have white or no color
const CATEGORY_FALLBACK_COLORS = [
    '#6366f1', // indigo
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#f59e0b', // amber
    '#10b981', // emerald
    '#06b6d4', // cyan
    '#f97316', // orange
    '#84cc16', // lime
];

// Check if a color is white or near-white
const isWhiteColor = (color: string | undefined): boolean => {
    if (!color) return true;
    const c = color.toLowerCase().trim();
    if (c === 'white' || c === '#fff' || c === '#ffffff') return true;
    // Check for near-white colors (e.g., #fafafa, #f5f5f5)
    if (c.match(/^#f[a-f0-9]f[a-f0-9]f[a-f0-9]$/i)) return true;
    return false;
};

// Get a consistent fallback color based on category name
const getFallbackColor = (name: string): string => {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    return CATEGORY_FALLBACK_COLORS[Math.abs(hash) % CATEGORY_FALLBACK_COLORS.length];
};

// Get category color, replacing white with a fallback
const getCategoryColor = (color: string | undefined, name: string): string => {
    if (isWhiteColor(color)) {
        return getFallbackColor(name);
    }
    return color!;
};

// Darker versions for percentage text
const COLORS_DARK = {
    income: '#14532d',
    expense: '#7f1d1d',
    deficit: '#9a3412',
    surplus: '#14532d',
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
    const [showCategoryDetail, setShowCategoryDetail] = useState(false);

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
            })
            .catch(err => {
                console.error('Error loading months:', err);
                setError('Kon periodes niet laden');
                setIsLoading(false);
            });
    }, [accountId]);

    const handlePeriodChange = (newStartDate: string, newEndDate: string) => {
        setStartDate(newStartDate);
        setEndDate(newEndDate);
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

        // Totaal = inkomsten (leidend)
        // De expense flows worden proportioneel geschaald naar het Totaal
        // Bij overschot: voeg Overschot toe zodat expense som = Totaal
        // Bij tekort: voeg Tekort toe - de flows worden allemaal naar Totaal geschaald
        const difference = totalIncome - totalExpense;
        if (difference > 0) {
            // Overschot: geld over, voeg toe aan expenses
            expenseBudgets.push({ name: 'Overschot', value: difference });
        } else if (difference < 0) {
            // Tekort: meer uitgegeven dan inkomsten
            // We voegen Tekort toe als visuele indicator
            expenseBudgets.push({ name: 'Tekort', value: Math.abs(difference) });
        }

        // Calculate expense total after adding Overschot/Tekort
        const expenseTotalWithBalance = expenseBudgets.reduce((s, b) => s + b.value, 0);
        const displayTotal = totalIncome;

        // Wide, short layout that fits any viewport
        const width = 1400;
        const height = 500;
        const padding = { top: 30, bottom: 30, left: 180, right: 180 };
        const nodeWidth = 24;
        const innerHeight = height - padding.top - padding.bottom;

        const columnX = [padding.left, width / 2 - nodeWidth / 2, width - padding.right - nodeWidth];

        // Calculate node heights - use equal distribution based on count, with max height
        const maxNodes = Math.max(incomeBudgets.length, expenseBudgets.length, 1);
        const nodeGap = 12;
        const availableForNodes = innerHeight - (maxNodes - 1) * nodeGap;
        const nodeHeight = Math.min(36, Math.max(20, availableForNodes / maxNodes));

        // Position income nodes evenly
        const incomeNodes: NodePosition[] = [];
        const totalIncomeNodesHeight = incomeBudgets.length * nodeHeight + (incomeBudgets.length - 1) * nodeGap;
        let incomeY = padding.top + (innerHeight - totalIncomeNodesHeight) / 2;

        incomeBudgets.forEach(b => {
            incomeNodes.push({ name: b.name, type: 'income', value: b.value, y: incomeY, height: nodeHeight, color: COLORS.income });
            incomeY += nodeHeight + nodeGap;
        });

        // Position expense nodes evenly
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
            value: displayTotal,
            y: padding.top,
            height: innerHeight,
            color: COLORS.total
        };

        // Build flows with proportional heights based on values
        const flows: FlowPath[] = [];

        // Two separate scales: income always fills the full Totaal, expenses scale to their sum
        const incomeFlowScale = innerHeight / displayTotal;
        const expenseFlowScale = innerHeight / expenseTotalWithBalance;

        // Income flows - always fill the full Totaal bar
        let incomeFlowY = padding.top;
        incomeNodes.forEach(node => {
            const flowHeightOnTotal = node.value * incomeFlowScale;
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

        // Expense flows - fill the full height (scaled to their sum including Overschot/Tekort)
        let expenseFlowY = padding.top;
        expenseNodes.forEach(node => {
            const flowHeightOnTotal = node.value * expenseFlowScale;
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

        return { width, height, nodeWidth, columnX, incomeNodes, totalNode, expenseNodes, flows, totalIncome, totalExpense, expenseTotalWithBalance };
    }, [data]);

    // Category detail layout: Expense Budgets → Categories (grouped by budget)
    const categoryLayout = useMemo(() => {
        if (!data) return null;

        // Find expense budgets and their categories from the data
        const expenseBudgetNodes: { index: number; name: string; id?: number }[] = [];
        const categoryNodeMap = new Map<number, { index: number; name: string; id?: number; color?: string }>();

        data.nodes.forEach((node, idx) => {
            if (node.type === 'expense_budget') {
                expenseBudgetNodes.push({ index: idx, name: node.name, id: node.id });
            } else if (node.type === 'expense_category') {
                categoryNodeMap.set(idx, { index: idx, name: node.name, id: node.id, color: node.color });
            }
        });

        // Find links from expense_budget to expense_category
        const budgetToCategoryLinks: { budgetIndex: number; categoryIndex: number; value: number }[] = [];
        data.links.forEach(link => {
            const sourceNode = data.nodes[link.source];
            const targetNode = data.nodes[link.target];
            if (sourceNode.type === 'expense_budget' && targetNode.type === 'expense_category') {
                budgetToCategoryLinks.push({
                    budgetIndex: link.source,
                    categoryIndex: link.target,
                    value: link.value
                });
            }
        });

        if (budgetToCategoryLinks.length === 0) return null;

        // Calculate totals per budget
        const budgetTotals = new Map<number, number>();
        budgetToCategoryLinks.forEach(link => {
            budgetTotals.set(link.budgetIndex, (budgetTotals.get(link.budgetIndex) || 0) + link.value);
        });

        // Filter budgets with actual data and sort by value
        const budgetsWithData = expenseBudgetNodes
            .filter(b => (budgetTotals.get(b.index) || 0) > 0)
            .map(b => ({ ...b, value: budgetTotals.get(b.index) || 0 }))
            .sort((a, b) => b.value - a.value);

        if (budgetsWithData.length === 0) return null;

        // Group categories by budget (in budget order) to minimize line crossings
        const orderedCategories: {
            index: number;
            name: string;
            value: number;
            color?: string;
            budgetIndex: number;
            budgetValue: number;
            valueInBudget: number;
            isFirstInBudget: boolean;
        }[] = [];
        const seenCategories = new Set<number>();

        budgetsWithData.forEach((budget, budgetIdx) => {
            // Get all categories for this budget, sorted by value
            const budgetCategories = budgetToCategoryLinks
                .filter(link => link.budgetIndex === budget.index && !seenCategories.has(link.categoryIndex))
                .sort((a, b) => b.value - a.value);

            budgetCategories.forEach((link, catIdxInBudget) => {
                const catNode = categoryNodeMap.get(link.categoryIndex);
                if (catNode && !seenCategories.has(link.categoryIndex)) {
                    // Calculate total value for this category across all budgets
                    const totalCatValue = budgetToCategoryLinks
                        .filter(l => l.categoryIndex === link.categoryIndex)
                        .reduce((sum, l) => sum + l.value, 0);

                    orderedCategories.push({
                        index: catNode.index,
                        name: catNode.name,
                        value: totalCatValue,
                        color: catNode.color,
                        budgetIndex: budget.index,
                        budgetValue: budget.value,
                        valueInBudget: link.value,
                        isFirstInBudget: catIdxInBudget === 0 && budgetIdx > 0
                    });
                    seenCategories.add(link.categoryIndex);
                }
            });
        });

        if (orderedCategories.length === 0) return null;

        const totalExpense = budgetsWithData.reduce((sum, b) => sum + b.value, 0);

        // Layout dimensions - dynamic height based on number of nodes
        const width = 1400;
        const padding = { top: 30, bottom: 30, left: 220, right: 220 };
        const nodeWidth = 24;
        const nodeHeight = 32;
        const nodeGap = 8;

        // Calculate required height based on the column with most nodes
        const maxNodes = Math.max(budgetsWithData.length, orderedCategories.length);
        const requiredHeight = maxNodes * nodeHeight + (maxNodes - 1) * nodeGap + padding.top + padding.bottom;
        const height = Math.max(500, requiredHeight);
        const innerHeight = height - padding.top - padding.bottom;

        const columnX = [padding.left, width - padding.right - nodeWidth];

        // Position budget nodes (left column) - distribute evenly
        const budgetNodes: NodePosition[] = [];
        const totalBudgetNodesHeight = budgetsWithData.length * nodeHeight + (budgetsWithData.length - 1) * nodeGap;
        let budgetY = padding.top + (innerHeight - totalBudgetNodesHeight) / 2;

        budgetsWithData.forEach(b => {
            budgetNodes.push({
                name: b.name,
                type: 'expense',
                value: b.value,
                y: budgetY,
                height: nodeHeight,
                color: COLORS.expense
            });
            budgetY += nodeHeight + nodeGap;
        });

        // Position category nodes (right column) - in budget-grouped order
        interface CategoryNodePosition extends NodePosition {
            budgetValue: number;
            valueInBudget: number;
            isFirstInBudget: boolean;
            percentOfTotal: number;
            percentOfBudget: number;
        }
        const categoryNodes: CategoryNodePosition[] = [];
        const totalCategoryNodesHeight = orderedCategories.length * nodeHeight + (orderedCategories.length - 1) * nodeGap;
        let categoryY = padding.top + (innerHeight - totalCategoryNodesHeight) / 2;

        orderedCategories.forEach(c => {
            categoryNodes.push({
                name: c.name,
                type: 'category',
                value: c.value,
                y: categoryY,
                height: nodeHeight,
                color: getCategoryColor(c.color, c.name),
                budgetValue: c.budgetValue,
                valueInBudget: c.valueInBudget,
                isFirstInBudget: c.isFirstInBudget,
                percentOfTotal: Math.round(c.value / totalExpense * 100),
                percentOfBudget: Math.round(c.valueInBudget / c.budgetValue * 100)
            });
            categoryY += nodeHeight + nodeGap;
        });

        // Build flows
        const flows: FlowPath[] = [];
        const budgetFlowOffsets = new Map<number, number>();
        const categoryFlowOffsets = new Map<number, number>();

        // Initialize offsets
        budgetsWithData.forEach((_, idx) => budgetFlowOffsets.set(idx, 0));
        orderedCategories.forEach((_, idx) => categoryFlowOffsets.set(idx, 0));

        // Create index mappings
        const budgetIndexToPosition = new Map<number, number>();
        budgetsWithData.forEach((b, idx) => budgetIndexToPosition.set(b.index, idx));

        const categoryIndexToPosition = new Map<number, number>();
        orderedCategories.forEach((c, idx) => categoryIndexToPosition.set(c.index, idx));

        // Process links in budget order, then by category position
        const sortedLinks = [...budgetToCategoryLinks].sort((a, b) => {
            const budgetPosA = budgetIndexToPosition.get(a.budgetIndex) ?? 999;
            const budgetPosB = budgetIndexToPosition.get(b.budgetIndex) ?? 999;
            if (budgetPosA !== budgetPosB) return budgetPosA - budgetPosB;
            const catPosA = categoryIndexToPosition.get(a.categoryIndex) ?? 999;
            const catPosB = categoryIndexToPosition.get(b.categoryIndex) ?? 999;
            return catPosA - catPosB;
        });

        sortedLinks.forEach(link => {
            const budgetPos = budgetIndexToPosition.get(link.budgetIndex);
            const categoryPos = categoryIndexToPosition.get(link.categoryIndex);

            if (budgetPos === undefined || categoryPos === undefined) return;

            const budgetNode = budgetNodes[budgetPos];
            const categoryNode = categoryNodes[categoryPos];

            // Calculate proportional heights
            const sourceFlowHeight = (link.value / budgetNode.value) * nodeHeight;
            const targetFlowHeight = (link.value / categoryNode.value) * nodeHeight;

            const sourceOffset = budgetFlowOffsets.get(budgetPos) || 0;
            const targetOffset = categoryFlowOffsets.get(categoryPos) || 0;

            flows.push({
                sourceY: budgetNode.y + sourceOffset,
                sourceHeight: sourceFlowHeight,
                targetY: categoryNode.y + targetOffset,
                targetHeight: targetFlowHeight,
                value: link.value,
                color: categoryNode.color,
                sourceName: budgetNode.name,
                targetName: categoryNode.name
            });

            budgetFlowOffsets.set(budgetPos, sourceOffset + sourceFlowHeight);
            categoryFlowOffsets.set(categoryPos, targetOffset + targetFlowHeight);
        });

        return { width, height, nodeWidth, columnX, budgetNodes, categoryNodes, flows, totalExpense };
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

    return (
        <div className="fixed inset-0 z-50 bg-black/80 flex items-center justify-center">
            {/* Modal container */}
            <div className="bg-white w-[95vw] h-[90vh] rounded-2xl shadow-2xl flex flex-col overflow-hidden">
                {/* Header */}
                <div className="flex-shrink-0 flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
                    <div className="flex items-center gap-6">
                        <h1 className="text-xl font-bold text-gray-800">Geldstroom Diagram</h1>

                        {/* Period picker */}
                        <PeriodPicker
                            months={months}
                            onChange={handlePeriodChange}
                            currentStartDate={startDate}
                            currentEndDate={endDate}
                        />

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

                        {/* Category detail toggle */}
                        <button
                            onClick={() => setShowCategoryDetail(!showCategoryDetail)}
                            className={`px-4 py-1.5 rounded-lg text-sm font-medium transition-all ${
                                showCategoryDetail
                                    ? 'bg-red-600 text-white'
                                    : 'bg-gray-200 text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            Categorieën
                        </button>
                    </div>

                    <div className="flex items-center gap-6">
                        {showCategoryDetail ? (
                            categoryLayout && (
                                <div className="flex gap-6 text-sm">
                                    <span className="flex items-center gap-2">
                                        <span className="w-3 h-3 rounded-full" style={{ backgroundColor: COLORS.expense }} />
                                        <span className="text-gray-500">Totaal uitgaven:</span>
                                        <span className="font-bold" style={{ color: COLORS.expense }}>{formatMoney(categoryLayout.totalExpense)}</span>
                                    </span>
                                </div>
                            )
                        ) : (
                            layout && (
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
                            )
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
                <div className="flex-1 overflow-auto p-4 min-h-0">
                    {isLoading ? (
                        <div className="flex items-center justify-center h-full">
                            <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600" />
                        </div>
                    ) : error ? (
                        <div className="flex items-center justify-center h-full">
                            <div className="text-red-500 text-xl">{error}</div>
                        </div>
                    ) : showCategoryDetail ? (
                        // Category detail view: Expense Budgets → Categories
                        !categoryLayout ? (
                            <div className="flex items-center justify-center h-full">
                                <div className="text-center text-gray-500">
                                    <p className="text-2xl font-medium">Geen categorie data beschikbaar</p>
                                    <p className="text-lg mt-2">Er zijn geen categorieën gekoppeld aan uitgaven budgetten</p>
                                </div>
                            </div>
                        ) : (
                            <svg
                                viewBox={`0 0 ${categoryLayout.width} ${categoryLayout.height}`}
                                preserveAspectRatio="xMinYMin meet"
                                style={{ minWidth: `${categoryLayout.width}px`, minHeight: `${categoryLayout.height}px` }}
                            >
                                {/* Flows */}
                                <g>
                                    {categoryLayout.flows.map((flow, i) => (
                                        <path
                                            key={i}
                                            d={flowPath(flow, categoryLayout.columnX[0], categoryLayout.columnX[1], categoryLayout.nodeWidth)}
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
                                    ))}
                                </g>

                                {/* Budget nodes (left column) */}
                                <g>
                                    {categoryLayout.budgetNodes.map((node, i) => (
                                        <g key={`budget-${i}`}>
                                            <rect
                                                x={categoryLayout.columnX[0]}
                                                y={node.y}
                                                width={categoryLayout.nodeWidth}
                                                height={node.height}
                                                fill={node.color}
                                                rx={4}
                                            />
                                            {node.height >= 20 && (
                                                <text
                                                    x={categoryLayout.columnX[0] + categoryLayout.nodeWidth / 2}
                                                    y={node.y + node.height / 2}
                                                    textAnchor="middle"
                                                    dominantBaseline="middle"
                                                    fontSize={9}
                                                    fontWeight={700}
                                                    fill={COLORS_DARK.expense}
                                                >
                                                    {Math.round(node.value / categoryLayout.totalExpense * 100)}%
                                                </text>
                                            )}
                                            <text
                                                x={categoryLayout.columnX[0] - 12}
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
                                                x={categoryLayout.columnX[0] - 12}
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

                                {/* Category nodes (right column) */}
                                <g>
                                    {categoryLayout.categoryNodes.map((node, i) => (
                                        <g key={`category-${i}`}>
                                            {/* Separator line between budget groups */}
                                            {node.isFirstInBudget && (
                                                <line
                                                    x1={categoryLayout.columnX[1] - 8}
                                                    y1={node.y - 4}
                                                    x2={categoryLayout.columnX[1] + categoryLayout.nodeWidth + 8}
                                                    y2={node.y - 4}
                                                    stroke="#eeeeee"
                                                    strokeWidth={0.5}
                                                />
                                            )}
                                            <rect
                                                x={categoryLayout.columnX[1]}
                                                y={node.y}
                                                width={categoryLayout.nodeWidth}
                                                height={node.height}
                                                fill={node.color}
                                                rx={4}
                                            />
                                            {node.height >= 20 && (
                                                <text
                                                    x={categoryLayout.columnX[1] + categoryLayout.nodeWidth / 2}
                                                    y={node.y + node.height / 2}
                                                    textAnchor="middle"
                                                    dominantBaseline="middle"
                                                    fontSize={9}
                                                    fontWeight={700}
                                                    fill="white"
                                                >
                                                    {node.percentOfBudget}%
                                                </text>
                                            )}
                                            <text
                                                x={categoryLayout.columnX[1] + categoryLayout.nodeWidth + 12}
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
                                                x={categoryLayout.columnX[1] + categoryLayout.nodeWidth + 12}
                                                y={node.y + node.height / 2 + 18}
                                                textAnchor="start"
                                                dominantBaseline="middle"
                                                fontSize={12}
                                                fill="#6b7280"
                                            >
                                                {formatMoney(node.value)}
                                            </text>
                                            {/* Invisible hover area for tooltip */}
                                            <rect
                                                x={categoryLayout.columnX[1]}
                                                y={node.y}
                                                width={categoryLayout.nodeWidth + 220}
                                                height={node.height}
                                                fill="transparent"
                                                onMouseEnter={e => setTooltip({
                                                    x: e.clientX,
                                                    y: e.clientY,
                                                    content: `${node.percentOfTotal}% van totale uitgaven`
                                                })}
                                                onMouseMove={e => setTooltip(t => t ? { ...t, x: e.clientX, y: e.clientY } : null)}
                                                onMouseLeave={() => setTooltip(null)}
                                                className="cursor-pointer"
                                            />
                                        </g>
                                    ))}
                                </g>
                            </svg>
                        )
                    ) : !layout ? (
                        <div className="flex items-center justify-center h-full">
                            <div className="text-center text-gray-500">
                                <p className="text-2xl font-medium">Geen flow data beschikbaar</p>
                                <p className="text-lg mt-2">Er zijn geen transacties in budgetten voor deze periode</p>
                            </div>
                        </div>
                    ) : (
                        <svg
                            viewBox={`0 0 ${layout.width} ${layout.height}`}
                            preserveAspectRatio="xMinYMin meet"
                            style={{ minWidth: `${layout.width}px`, minHeight: `${layout.height}px` }}
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
                                        {node.height >= 20 && (
                                            <text
                                                x={layout.columnX[0] + layout.nodeWidth / 2}
                                                y={node.y + node.height / 2}
                                                textAnchor="middle"
                                                dominantBaseline="middle"
                                                fontSize={9}
                                                fontWeight={700}
                                                fill={COLORS_DARK.income}
                                            >
                                                {Math.round(node.value / layout.totalIncome * 100)}%
                                            </text>
                                        )}
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
                                {/* Label background */}
                                <rect
                                    x={layout.columnX[1] + layout.nodeWidth / 2 - 50}
                                    y={layout.totalNode.y + layout.totalNode.height / 2 - 28}
                                    width={100}
                                    height={56}
                                    fill={COLORS.total}
                                    rx={6}
                                />
                                <text
                                    x={layout.columnX[1] + layout.nodeWidth / 2}
                                    y={layout.totalNode.y + layout.totalNode.height / 2 - 8}
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    fontSize={16}
                                    fontWeight={700}
                                    fill="white"
                                >
                                    Totaal
                                </text>
                                <text
                                    x={layout.columnX[1] + layout.nodeWidth / 2}
                                    y={layout.totalNode.y + layout.totalNode.height / 2 + 14}
                                    textAnchor="middle"
                                    dominantBaseline="middle"
                                    fontSize={13}
                                    fontWeight={600}
                                    fill="white"
                                >
                                    {formatMoney(layout.totalIncome)}
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
                                        {node.height >= 20 && (
                                            <text
                                                x={layout.columnX[2] + layout.nodeWidth / 2}
                                                y={node.y + node.height / 2}
                                                textAnchor="middle"
                                                dominantBaseline="middle"
                                                fontSize={9}
                                                fontWeight={700}
                                                fill={node.name === 'Overschot' ? COLORS_DARK.surplus : node.name === 'Tekort' ? COLORS_DARK.deficit : COLORS_DARK.expense}
                                            >
                                                {Math.round(node.value / layout.expenseTotalWithBalance * 100)}%
                                            </text>
                                        )}
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
