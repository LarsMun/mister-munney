import { formatMoney } from '../../../shared/utils/MoneyFormat';
import { motion } from 'framer-motion';

interface HeroSectionProps {
    currentBalance: number;
    monthlyChange: number;
    averageBalance: number;
}

export default function HeroSection({ 
    currentBalance, 
    monthlyChange, 
    averageBalance 
}: HeroSectionProps) {
    const isPositiveChange = monthlyChange >= 0;
    
    return (
        <motion.div
            initial={{ opacity: 0, y: -20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
            className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-xl p-8 text-white mb-8"
        >
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div>
                    <h2 className="text-sm font-medium text-blue-200 mb-2">Huidige Balans</h2>
                    <p className="text-5xl font-bold mb-4">{formatMoney(currentBalance)}</p>
                    <div className="flex flex-wrap gap-4 text-sm">
                        <div className="flex items-center gap-2">
                            <span className={`px-3 py-1 rounded-full ${
                                isPositiveChange 
                                    ? 'bg-green-500/20 text-green-100' 
                                    : 'bg-red-500/20 text-red-100'
                            }`}>
                                {isPositiveChange ? '📈' : '📉'} Deze maand: {isPositiveChange ? '+' : ''}{formatMoney(monthlyChange)}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="px-3 py-1 rounded-full bg-blue-500/20 text-blue-100">
                                📊 Gemiddeld: {formatMoney(averageBalance)}
                            </span>
                        </div>
                    </div>
                </div>
                
                <div className="flex flex-col gap-3">
                    <div className="bg-white/10 backdrop-blur-sm rounded-lg px-4 py-3 text-center">
                        <p className="text-xs text-blue-200 mb-1">Verandering</p>
                        <p className={`text-2xl font-bold ${isPositiveChange ? 'text-green-300' : 'text-red-300'}`}>
                            {isPositiveChange ? '+' : ''}{((monthlyChange / Math.abs(averageBalance)) * 100).toFixed(1)}%
                        </p>
                    </div>
                </div>
            </div>
        </motion.div>
    );
}
