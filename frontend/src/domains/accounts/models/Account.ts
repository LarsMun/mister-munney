export type AccountType = 'CHECKING' | 'SAVINGS';

export type Account = {
    id: number;
    name: string;
    accountNumber: string;
    isDefault: boolean;
    type: AccountType;
};

export type UpdateAccountRequest = {
    name: string;
    type?: AccountType;
};
