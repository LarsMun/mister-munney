/**
 * Represents a node in the Sankey diagram
 */
export interface SankeyNode {
    name: string;
    type: 'income_budget' | 'income_category' | 'total' | 'expense_budget' | 'expense_category';
    id?: number;
    color?: string;
}

/**
 * Represents a link (flow) between two nodes
 */
export interface SankeyLink {
    source: number;
    target: number;
    value: number;
}

/**
 * Complete Sankey flow data from the API
 */
export interface SankeyFlowData {
    nodes: SankeyNode[];
    links: SankeyLink[];
    mode: 'actual' | 'median';
    totalIncome: number;
    totalExpense: number;
    netFlow: number;
}

/**
 * Mode for Sankey flow data
 */
export type SankeyMode = 'actual' | 'median';
