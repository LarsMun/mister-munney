import { Treemap, Tooltip, ResponsiveContainer } from "recharts";
import type { TooltipProps } from "recharts";
import { formatMoney } from "../../../shared/utils/MoneyFormat";

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

interface TreeMapDataItem {
    name: string;
    value: number;
    transactionCount: number;
    fill: string;
}

interface ContentProps {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    name?: string;
    value?: number;
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

    const CustomTooltip = ({ payload }: TooltipProps<number, string>) => {
        if (!payload || !payload.length) return null;
        const data = payload[0].payload as TreeMapDataItem;
        return (
            <div className="bg-white p-2 rounded shadow text-xs text-gray-700">
                <div className="font-semibold mb-1">{data.name}</div>
                <div>Bedrag: <span className="font-medium">{formatMoney(data.value)}</span></div>
                <div>Aantal transacties: <span className="font-medium">{data.transactionCount}</span></div>
            </div>
        );
    };

    const renderContent = (props: ContentProps) => {
        const { x = 0, y = 0, width = 0, height = 0, name = '' } = props;
        
        if (!width || !height || width < 10 || height < 10) return null;
        
        const data = treeData.children.find(item => item.name === name);
        if (!data) return null;

        return (
            <g>
                <rect
                    x={x}
                    y={y}
                    width={width}
                    height={height}
                    style={{
                        fill: data.fill,
                        stroke: '#fff',
                        strokeWidth: 2,
                        cursor: 'pointer',
                    }}
                    onClick={() => onSelectCategory(name)}
                />
                {width > 50 && height > 30 && (
                    <text
                        x={x + width / 2}
                        y={y + height / 2}
                        textAnchor="middle"
                        fill="#000"
                        fontSize={12}
                        fontWeight="bold"
                    >
                        {name}
                    </text>
                )}
            </g>
        );
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
                    content={renderContent as any}
                >
                    <Tooltip content={<CustomTooltip />} />
                </Treemap>
            </ResponsiveContainer>
        </div>
    );
}
