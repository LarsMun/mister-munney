// src/utils/TreeMapChartData.ts

import type { Transaction } from '../models/Transaction';

export function prepareTreeChartData(transactions: Transaction[]) {
    // Verwerk de gegevens per categorie
    const data = transactions.reduce((acc, tx) => {
        if (!tx.category) return acc;

        const categoryName = tx.category.name;
        const categoryColor = tx.category.color;  // Verkrijg de kleur van de categorie
        const existingCategory = acc.find((item: any) => item.name === categoryName);

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
    const treeData = {
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