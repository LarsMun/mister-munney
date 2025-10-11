import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';

interface QuickActionsProps {
    onImportTransactions?: () => void;
    uncategorizedCount?: number;
}

export default function QuickActions({ onImportTransactions, uncategorizedCount = 0 }: QuickActionsProps) {
    const actions = [
        {
            label: 'Transacties Importeren',
            icon: '📥',
            color: 'bg-blue-600 hover:bg-blue-700',
            onClick: onImportTransactions,
            isButton: true
        },
        {
            label: 'Bekijk Transacties',
            icon: '📊',
            color: 'bg-green-600 hover:bg-green-700',
            to: '/transactions',
            badge: uncategorizedCount > 0 ? uncategorizedCount : undefined
        },
        {
            label: 'Beheer Patronen',
            icon: '🎯',
            color: 'bg-purple-600 hover:bg-purple-700',
            to: '/patterns'
        },
        {
            label: 'Budget Overzicht',
            icon: '💰',
            color: 'bg-orange-600 hover:bg-orange-700',
            to: '/budgets'
        }
    ];

    return (
        <div className="bg-white rounded-lg shadow-md p-6">
            <h3 className="text-lg font-semibold text-gray-800 mb-4">Snelle Acties</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {actions.map((action, index) => (
                    <motion.div
                        key={action.label}
                        initial={{ opacity: 0, scale: 0.9 }}
                        animate={{ opacity: 1, scale: 1 }}
                        transition={{ duration: 0.2, delay: index * 0.05 }}
                    >
                        {action.isButton ? (
                            <button
                                onClick={action.onClick}
                                className={`${action.color} text-white px-4 py-3 rounded-lg transition-all shadow hover:shadow-md w-full text-left flex items-center justify-between`}
                            >
                                <span className="flex items-center gap-3">
                                    <span className="text-2xl">{action.icon}</span>
                                    <span className="font-medium">{action.label}</span>
                                </span>
                            </button>
                        ) : (
                            <Link
                                to={action.to!}
                                className={`${action.color} text-white px-4 py-3 rounded-lg transition-all shadow hover:shadow-md flex items-center justify-between`}
                            >
                                <span className="flex items-center gap-3">
                                    <span className="text-2xl">{action.icon}</span>
                                    <span className="font-medium">{action.label}</span>
                                </span>
                                {action.badge && (
                                    <span className="bg-white text-gray-800 text-xs font-bold px-2.5 py-1 rounded-full">
                                        {action.badge}
                                    </span>
                                )}
                            </Link>
                        )}
                    </motion.div>
                ))}
            </div>
        </div>
    );
}
