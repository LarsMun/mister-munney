import React from "react";
import { Treemap, Tooltip, ResponsiveContainer } from "recharts";

interface Props {
    treeMapData: {
        debit: {
            categoryName: string;
            totalAmount: number;
            transactionCount: number;
            categoryColor: string;
        }[];
    };
    onSelectCategory: (categoryName: string) => void;
}

export default function TreeMapChartExpenses({ treeMapData, onSelectCategory }: Props) {
    const treeData = {
        children: treeMapData.debit.map((item) => ({
            name: item.categoryName,
            value: item.totalAmount,
            transactionCount: item.transactionCount,
            fill: item.categoryColor,
        })),
    };

    return (
        <div className="h-96">
            <h2 className="text-center font-semibold mb-4">Uitgaven per Categorie</h2>
            <ResponsiveContainer width="100%" height="100%">
                <Treemap
                    data={treeData.children}
                    dataKey="value"
                    nameKey="name"
                    aspectRatio={16 / 9}
                    animationDuration={250}
                    cursor="pointer"
                    onClick={(node) => {
                        if (node && node.name) {
                            onSelectCategory(node.name);
                        }
                    }}
                >
                    <Tooltip
                        content={({ payload }: any) => {
                            if (!payload || !payload.length) return null;
                            const data = payload[0].payload;
                            return (
                                <div className="bg-white p-2 rounded shadow text-xs text-gray-700">
                                    <div className="font-semibold mb-1">{data.name}</div>
                                    <div>Bedrag: <span className="font-medium">€ {data.value.toFixed(2)}</span></div>
                                    <div>Aantal transacties: <span className="font-medium">{data.transactionCount}</span></div>
                                </div>
                            );
                        }}
                    />
                </Treemap>
            </ResponsiveContainer>
        </div>
    );
}