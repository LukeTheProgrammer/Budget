export type OverCategory = {
    id: number;
    name: string;
    color: string | null;
    spent_cents: number;
    cap_cents: number;
    over_cents: number;
    change_cents: number;
    transaction_count: number;
};

export type CompositionSlice = {
    name: string;
    color: string | null;
    over_cents: number;
    percent: number;
};

export type ChangeRow = {
    name: string;
    color: string | null;
    previous_cents: number;
    current_cents: number;
    change_cents: number;
};

export type InsightsSummary = {
    total_overage_cents: number;
    categories_over: number;
    categories_total: number;
    vs_previous_cents: number;
    vs_previous_percent: number | null;
    biggest_contributor: OverCategory | null;
    largest_charge: {
        amount_cents: number;
        merchant: string;
        category: string | null;
    } | null;
};

export type SpendingChangesData = {
    rows: ChangeRow[];
    increases_cents: number;
    decreases_cents: number;
    net_cents: number;
};
