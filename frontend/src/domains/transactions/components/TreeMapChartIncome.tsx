import { Treemap, Tooltip, ResponsiveContainer } from "recharts";
import type { TooltipProps } from "recharts";
import { formatMoney } from "../../../shared/utils/MoneyFormat";
import type { TreeMapCategoryData } from "../models/TreeMapDataType";

interface Props {
    categoryData: TreeMapCategoryData[];
}

interface TreeMapItem {
    name: string;
    value: number;
    percent: string;
    color: string;
}

interface ContentProps {
    x?: number;
    y?: number;
    width?: number;
    height?: number;
    name?: string;
}

export default function TreeMapChartIncome({ categoryData }: Props) {
    // Transform TreeMapCategoryData to treemap format
    const treeData = {
        name: "Root",
        children: categoryData.map(cat => ({
            name: cat.categoryName,
            value: cat.totalAmount,
            percent: `${cat.percentageOfTotal.toFixed(2)}%`,
            color: cat.categoryColor,
        }))
    };

    const CustomTooltip = ({ payload }: TooltipProps<number, string>) => {
        if (!payload || !payload.length) return null;
        const data = payload[0].payload as TreeMapItem;
        return (
            <div className="bg-white p-2 border rounded shadow text-xs">
                <div><strong>{data.name}</strong></div>
                <div>{formatMoney(data.value)}</div>
                <div>{data.percent} van totaal</div>
            </div>
        );
    };

    const renderContent = (props: ContentProps) => {
        const { x = 0, y = 0, width = 0, height = 0, name = '' } = props;
        
        if (!width || !height || width < 10 || height < 10) return null;
        
        const item = treeData.children.find(c => c.name === name);
        if (!item) return null;

        return (
            <g>
                <rect
                    x={x}
                    y={y}
                    width={width}
                    height={height}
                    style={{
                        fill: item.color || "#8884d8",
                        stroke: '#fff',
                        strokeWidth: 2,
                        fillOpacity: 0.8,
                    }}
                />
                {width > 50 && height > 30 && (
                    <text
                        x={x + width / 2}
                        y={y + height / 2}
                        textAnchor="middle"
                        fill="#000"
                        fontSize={12}
                    >
                        {name}
                    </text>
                )}
            </g>
        );
    };

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
                    content={renderContent as any}
                >
                    <Tooltip content={<CustomTooltip />} />
                </Treemap>
            </ResponsiveContainer>
        </div>
    );
}
