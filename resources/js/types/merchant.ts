export type MerchantAlias = {
    id: number;
    name: string;
};

export type MerchantRule = {
    id: number;
    match_type: 'prefix' | 'regex';
    pattern: string;
};

export type MerchantTag = {
    slug: string;
    name: string;
};

export type Merchant = {
    id: number;
    name: string;
    confirmed: boolean;
    suggested_name: string | null;
    suggested_prefix: string | null;
    category_id: number | null;
    transactions_count: number;
    transactions_sum: number;
    aliases: MerchantAlias[];
    rules: MerchantRule[];
    default_tags: MerchantTag[];
};
