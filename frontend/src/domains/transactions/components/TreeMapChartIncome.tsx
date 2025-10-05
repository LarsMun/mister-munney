import React from "react";
import { Treemap, Tooltip, ResponsiveContainer, Cell } from "recharts";
import { prepareTreeChartData } from "../utils/TreeMapChartData.ts";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import type { Transaction } from "../models/Transaction";

interface Props {
    transactions: Transaction[];
}

export default function TreeMapChartIncome({ transactions }: Props) {
    const treeData = prepareTreeChartData(transactions);

    return (
        <div className="h-96">
            <h2 className="text-center font-semibold mb-4">Inkomsten per Categorie (TreeMap)</h2>
            <ResponsiveContainer width="100%" height="100%">
                <Treemap
                    data={treeData.children}
                    dataKey="value"
                    nameKey="name"
                    stroke="#fff"
                    aspectRatio={4 / 3}
                >
                    {treeData.children.map((entry, index) => (
                        <Cell
                            key={`cell-${index}`}
                            fill={entry.color || "#8884d8"}
                            label={entry.name}
                            textAnchor="middle"
                            fillOpacity={0.8}
                        />
                    ))}
                    <Tooltip
                        content={({ payload }: any) => {
                            if (payload && payload.length) {
                                const { name, value, percent } = payload[0].payload;
                                return (
                                    <div className="bg-white p-2 border rounded shadow text-xs">
                                        <div><strong>{name}</strong></div>
                                        <div>{formatMoney(value)}</div>
                                        <div>{percent} van totaal</div>
                                    </div>
                                );
                            }
                            return null;
                        }}
                    />
                </Treemap>
            </ResponsiveContainer>
        </div>
    );
}