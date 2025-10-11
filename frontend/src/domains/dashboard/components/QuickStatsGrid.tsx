import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { motion } from 'framer-motion';

interface QuickStatsGridProps {
    startBalance: number;
    endBalance: number;
    totalDebit: number;
    totalCredit: number;
    netTotal: number;
    transactionCount: number;
}

export default function QuickStatsGrid({
    startBalance,
    endBalance,
    totalDebit,
    totalCredit,
    netTotal,
    transactionCount
}: QuickStatsGridProps) {
    const stats = [
        {
            label: 'Start Saldo',
            value: startBalance,
            icon: '💰',
            color: 'bg-blue-50 border-blue-200',
            textColor: 'text-blue-700'
        },
        {
            label: 'Eind Saldo',
            value: endBalance,
            icon: '🧾',
            color: 'bg-green-50 border-green-200',
            textColor: 'text-green-700'
        },
        {
            label: 'Uitgaven',
            value: totalDebit,
            icon: '📉',
            color: 'bg-red-50 border-red-200',
            textColor: 'text-red-700',
            prefix: '-'
        },
        {
            label: 'Inkomsten',
            value: totalCredit,
            icon: '📈',
            color: 'bg-emerald-50 border-emerald-200',
            textColor: 'text-emerald-700',
            prefix: '+'
        },
        {
            label: 'Netto Verschil',
            value: netTotal,
            icon: netTotal >= 0 ? '✅' : '⚠️',
            color: netTotal >= 0 ? 'bg-green-50 border-green-200' : 'bg-orange-50 border-orange-200',
            textColor: netTotal >= 0 ? 'text-green-700' : 'text-orange-700',
            prefix: netTotal >= 0 ? '+' : ''
        },
        {
            label: 'Transacties',
            value: transactionCount,
            icon: '📊',
            color: 'bg-purple-50 border-purple-200',
            textColor: 'text-purple-700',
            isCount: true
        }
    ];

    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {stats.map((stat, index) => (
                <motion.div
                    key={stat.label}
                    initial={{ opacity: 0, y: 20 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ duration: 0.3, delay: index * 0.05 }}
                    className={`${stat.color} border-2 rounded-lg p-4 hover:shadow-md transition-shadow`}
                >
                    <div className="flex items-start justify-between mb-2">
                        <span className="text-2xl">{stat.icon}</span>
                        <span className="text-xs text-gray-500 font-medium">{stat.label}</span>
                    </div>
                    <p className={`text-2xl font-bold ${stat.textColor}`}>
                        {stat.isCount 
                            ? stat.value 
                            : `${stat.prefix || ''}${formatMoney(stat.value)}`
                        }
                    </p>
                </motion.div>
            ))}
        </div>
    );
}
