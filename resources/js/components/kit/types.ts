export type DataTableColumn = {
    key: string;
    label: string;
    sortable?: boolean;
    align?: 'left' | 'right';
    class?: string;
};

export type FilterTab = {
    value: string;
    label: string;
    count?: number;
};

export type SelectFilter = {
    key: string;
    label: string;
    options: Array<{ value: string; label: string }>;
    value?: string | null;
};

export type DateRange = {
    from: string | null;
    to: string | null;
};
