<script setup lang="ts">
import { computed } from 'vue';
import {
    rowPaginationFeature,
    rowSortingFeature,
    tableFeatures,
    useTable,
    type RowData,
} from '@tanstack/vue-table';
import { ArrowDown, ArrowUp, ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import EmptyState from '@/components/kit/EmptyState.vue';
import type { DataTableColumn } from '@/components/kit/types';
import { updateQuery } from '@/lib/query';

type Props = {
    data: Array<Record<string, unknown>>;
    columns: DataTableColumn[];
    total?: number;
    page?: number;
    perPage?: number;
    sort?: string | null;
    direction?: 'asc' | 'desc';
    loading?: boolean;
    emptyTitle?: string;
    emptyDescription?: string;
    reloadProps?: string[];
};

const props = withDefaults(defineProps<Props>(), {
    total: undefined,
    page: 1,
    perPage: 10,
    sort: null,
    direction: 'asc',
    loading: false,
    emptyTitle: 'Nothing here yet',
    emptyDescription: 'Try changing the filters or add the first record.',
    reloadProps: () => [],
});

const features = tableFeatures({ rowPaginationFeature, rowSortingFeature });

const tableColumns = computed(() =>
    props.columns.map((column) => ({
        accessorKey: column.key,
        header: column.label,
        enableSorting: column.sortable ?? false,
    })),
);

const tableData = computed(() => props.data);
const rowCount = computed(() => props.total ?? props.data.length);
const tableState = computed(() => ({
    sorting: props.sort
        ? [{ id: props.sort, desc: props.direction === 'desc' }]
        : [],
    pagination: {
        pageIndex: Math.max(0, props.page - 1),
        pageSize: props.perPage,
    },
}));

function applyQuery(params: Record<string, string | number | null>) {
    updateQuery(params, props.reloadProps);
}

const table = useTable({
    features,
    columns: tableColumns,
    data: tableData,
    rowCount,
    manualPagination: true,
    manualSorting: true,
    state: tableState,
    onSortingChange: (updater) => {
        const current = tableState.value.sorting;
        const next = typeof updater === 'function' ? updater(current) : updater;
        const first = next[0];

        applyQuery({
            sort: first?.id ?? null,
            direction: first?.desc ? 'desc' : 'asc',
            page: null,
        });
    },
    onPaginationChange: (updater) => {
        const current = tableState.value.pagination;
        const next = typeof updater === 'function' ? updater(current) : updater;

        applyQuery({
            page: next.pageIndex === 0 ? null : next.pageIndex + 1,
            per_page: next.pageSize === 10 ? null : next.pageSize,
        });
    },
});

// Keep paging reachable on a page past the end (stale URL, shrunken result).
const showFooter = computed(
    () =>
        !props.loading &&
        (props.data.length > 0 || (props.page > 1 && rowCount.value > 0)),
);

const pageCount = computed(() =>
    Math.max(1, Math.ceil(rowCount.value / props.perPage)),
);

function asRecord(row: RowData): Record<string, unknown> {
    return row as Record<string, unknown>;
}

function cellValue(row: RowData, key: string): unknown {
    return asRecord(row)[key];
}

function toggleSort(column: DataTableColumn) {
    if (!column.sortable) {
        return;
    }

    const nextDirection =
        props.sort === column.key && props.direction === 'asc' ? 'desc' : 'asc';

    applyQuery({
        sort: column.key,
        direction: nextDirection,
        page: null,
    });
}

function goToPage(page: number) {
    if (page < 1 || page > pageCount.value || page === props.page) {
        return;
    }

    applyQuery({ page: page === 1 ? null : page });
}

function changePerPage(event: Event) {
    const value = Number((event.target as HTMLSelectElement).value);
    applyQuery({ per_page: value === 10 ? null : value, page: null });
}
</script>

<template>
    <div class="overflow-hidden rounded-xl border bg-card">
        <div v-if="loading" class="space-y-3 p-4">
            <Skeleton v-for="row in 5" :key="row" class="h-10 w-full" />
        </div>

        <EmptyState
            v-else-if="data.length === 0"
            :title="emptyTitle"
            :description="emptyDescription"
        >
            <template v-if="$slots.empty" #action>
                <slot name="empty" />
            </template>
        </EmptyState>

        <template v-else>
            <div class="hidden md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                v-for="column in columns"
                                :key="column.key"
                                :class="[
                                    column.align === 'right' && 'text-right',
                                    column.class,
                                ]"
                            >
                                <button
                                    v-if="column.sortable"
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-foreground"
                                    @click="toggleSort(column)"
                                >
                                    {{ column.label }}
                                    <ArrowUp
                                        v-if="
                                            sort === column.key &&
                                            direction === 'asc'
                                        "
                                        class="size-3.5"
                                    />
                                    <ArrowDown
                                        v-else-if="
                                            sort === column.key &&
                                            direction === 'desc'
                                        "
                                        class="size-3.5"
                                    />
                                </button>
                                <span v-else>{{ column.label }}</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                        >
                            <TableCell
                                v-for="column in columns"
                                :key="column.key"
                                :class="[
                                    column.align === 'right' && 'text-right',
                                    column.class,
                                ]"
                            >
                                <slot
                                    name="cell"
                                    :row="asRecord(row.original)"
                                    :column="column"
                                    :value="cellValue(row.original, column.key)"
                                >
                                    {{ cellValue(row.original, column.key) }}
                                </slot>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="divide-y md:hidden">
                <div
                    v-for="row in table.getRowModel().rows"
                    :key="row.id"
                    class="space-y-2 p-4"
                >
                    <div
                        v-for="column in columns"
                        :key="column.key"
                        class="flex items-start justify-between gap-3 text-sm"
                    >
                        <span class="text-muted-foreground">{{
                            column.label
                        }}</span>
                        <span class="text-right">
                            <slot
                                name="cell"
                                :row="asRecord(row.original)"
                                :column="column"
                                :value="cellValue(row.original, column.key)"
                            >
                                {{ cellValue(row.original, column.key) }}
                            </slot>
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <div
            v-if="showFooter"
            class="flex flex-col gap-3 border-t p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-2 text-muted-foreground">
                <span>Rows per page</span>
                <select
                    class="h-8 rounded-md border bg-background px-2"
                    :value="perPage"
                    @change="changePerPage"
                >
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                </select>
            </div>
            <div class="flex items-center justify-between gap-2 sm:justify-end">
                <span class="text-muted-foreground">
                    Page {{ page }} of {{ pageCount }}
                </span>
                <div class="flex gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        :disabled="page <= 1"
                        aria-label="Previous page"
                        @click="goToPage(page - 1)"
                    >
                        <ChevronLeft class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        :disabled="page >= pageCount"
                        aria-label="Next page"
                        @click="goToPage(page + 1)"
                    >
                        <ChevronRight class="size-4" />
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
