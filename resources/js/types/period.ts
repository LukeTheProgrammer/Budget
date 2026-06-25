export type SessionPeriodType =
    | 'this_month'
    | 'last_month'
    | 'last_3_months'
    | 'last_6_months'
    | 'last_12_months'
    | 'ytd'
    | 'custom';

export type SharedPeriod = {
    type: SessionPeriodType;
    start: string | null;
    end: string | null;
    label: string;
};
