export type Account = {
    id: number;
    name: string;
    accountNumber: string;
    isDefault: boolean;
};

export type UpdateAccountRequest = {
    name: string;
};
