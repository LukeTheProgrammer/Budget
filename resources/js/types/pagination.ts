export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Pagination = {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: PaginationLink[];
};
