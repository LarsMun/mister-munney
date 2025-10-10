import { Account } from '../../../domains/accounts/models/Account';

interface AccountSelectorProps {
    accounts: Account[];
    selectedAccountId: number | null;
    onAccountChange: (accountId: number) => void;
}

export default function AccountSelector({ 
    accounts, 
    selectedAccountId, 
    onAccountChange 
}: AccountSelectorProps) {
    const selectedAccount = accounts.find(a => a.id === selectedAccountId);

    const getDisplayName = (account: Account) => {
        return account.name || account.accountNumber;
    };

    // Sorteer: default bovenaan, daarna alfabetisch op naam/nummer
    const sortedAccounts = [...accounts].sort((a, b) => {
        if (a.isDefault) return -1;
        if (b.isDefault) return 1;
        const nameA = getDisplayName(a);
        const nameB = getDisplayName(b);
        return nameA.localeCompare(nameB);
    });

    return (
        <div className="relative inline-block">
            {/* Bank icon links in dropdown */}
            <div className="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none z-10">
                <svg 
                    className="w-4 h-4 text-gray-500" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path 
                        strokeLinecap="round" 
                        strokeLinejoin="round" 
                        strokeWidth={2} 
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" 
                    />
                </svg>
            </div>
            <select
                value={selectedAccountId || ''}
                onChange={(e) => onAccountChange(Number(e.target.value))}
                className="
                    appearance-none
                    bg-white/95
                    text-gray-900 
                    font-semibold
                    pl-10 pr-10 py-2.5
                    rounded-lg 
                    border-2 border-white/30
                    hover:border-white/50
                    hover:bg-white
                    focus:outline-none 
                    focus:ring-2 
                    focus:ring-white/60
                    focus:border-white/60
                    focus:bg-white
                    transition-all
                    cursor-pointer
                    shadow-md
                    hover:shadow-lg
                    min-w-[240px]
                    max-w-[320px]
                    text-sm
                "
            >
                {sortedAccounts.map((acc) => {
                    const displayName = getDisplayName(acc);
                    // Als er een naam is, toon ook het rekeningnummer
                    const fullDisplay = acc.name 
                        ? `${displayName} (${acc.accountNumber})` 
                        : displayName;
                    
                    return (
                        <option 
                            key={acc.id} 
                            value={acc.id}
                            style={{ fontWeight: acc.isDefault ? 'bold' : 'normal' }}
                        >
                            {fullDisplay}
                        </option>
                    );
                })}
            </select>
            
            {/* Custom arrow icon rechts */}
            <div className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none z-10">
                <svg 
                    className="w-4 h-4 text-gray-500" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                >
                    <path 
                        strokeLinecap="round" 
                        strokeLinejoin="round" 
                        strokeWidth={2} 
                        d="M19 9l-7 7-7-7" 
                    />
                </svg>
            </div>
        </div>
    );
}
