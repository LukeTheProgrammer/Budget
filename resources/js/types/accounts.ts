export type AccountListItem = {
    id: number;
    name: string;
    type: string | null;
    last_four: string | null;
    currency: string;
    balance_cents: number | null;
    is_linked: boolean;
    institution_name: string | null;
};

export type AccountTypeOption = {
    value: string;
    label: string;
};
