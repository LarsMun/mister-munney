// src/utils/TreeMapChartData.ts

import type { Transaction } from '../models/Transaction';

interface TreeMapItem {
    name: string;
    value: number;
    color: string | null | undefined;
}

interface TreeMapChild {
    name: string;
    value: number;
    percent: string;
    color: string | null | undefined;
}

interface TreeMapData {
    name: string;
    children: TreeMapChild[];
}

export function prepareTreeChartData(transactions: Transaction[]): TreeMapData {
    // Verwerk de gegevens per categorie
    const data = transactions.reduce<TreeMapItem[]>((acc, tx) => {
        if (!tx.category) return acc;

        const categoryName = tx.category.name;
        const categoryColor = tx.category.color;  // Verkrijg de kleur van de categorie
        const existingCategory = acc.find((item) => item.name === categoryName);

        if (existingCategory) {
            existingCategory.value += tx.amount;
        } else {
            acc.push({
                name: categoryName,
                value: tx.amount,
                color: categoryColor,  // Voeg de kleur toe
            });
        }

        return acc;
    }, []);

    const totalAmount = data.reduce((sum, item) => sum + item.value, 0);

    // Organiseer de data als een boomstructuur
    const treeData: TreeMapData = {
        name: "Root",
        children: data.map(item => ({
            name: item.name,
            value: item.value,
            percent: ((item.value / totalAmount) * 100).toFixed(2) + "%",
            color: item.color,  // Voeg de kleur toe aan de children
        })),
    };

    return treeData;
}
