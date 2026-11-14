<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DataTable from '@/components/kit/DataTable.vue';
import DateInput from '@/components/kit/DateInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import type { DataTableColumn } from '@/components/kit/types';
import { formatDateTime } from '@/lib/format';

type Option = { value: string; label: string };
type Props = {
    rows: App.Data.AuditLogRowData[];
    total: number;
    currentPage: number;
    perPage: number;
    filters: {
        user: number | null;
        entity: string | null;
        from: string | null;
        to: string | null;
    };
    users: Option[];
    entities: Option[];
};

const props = defineProps<Props>();
const columns: DataTableColumn[] = [
    { key: 'createdAt', label: 'When' },
    { key: 'causerName', label: 'User' },
    { key: 'event', label: 'Event' },
    { key: 'subjectType', label: 'Entity' },
    { key: 'reason', label: 'Reason' },
    { key: 'changes', label: 'Changes' },
];

const user = ref(props.filters.user ? String(props.filters.user) : 'all');
const entity = ref(props.filters.entity ?? 'all');
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function apply() {
    router.get(
        '/audit-log',
        {
            user: user.value === 'all' ? undefined : user.value,
            entity: entity.value === 'all' ? undefined : entity.value,
            from: from.value || undefined,
            to: to.value || undefined,
        },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function changeSummary(changes: unknown): string {
    if (changes === null || typeof changes !== 'object') {
        return '—';
    }

    const fields = changes as Record<string, unknown>;
    const attributes =
        fields['attributes'] && typeof fields['attributes'] === 'object'
            ? (fields['attributes'] as Record<string, unknown>)
            : fields;

    return Object.entries(attributes)
        .map(([key, value]) => `${key}: ${String(value ?? '—')}`)
        .join(', ');
}
</script>

<template>
    <Head title="Audit log" />
    <AppLayout :breadcrumbs="[{ title: 'Audit log', href: '/audit-log' }]">
        <div class="flex flex-1 flex-col gap-4 p-4">
            <PageHeader
                title="Audit log"
                description="Read-only history of changes to money, contracts, dues, payments, users and settings."
            />

            <div
                class="flex flex-col gap-2 rounded-xl border bg-card p-3 sm:flex-row sm:items-end"
            >
                <div class="min-w-44">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >User</label
                    >
                    <Select v-model="user">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All users</SelectItem>
                            <SelectItem
                                v-for="option in props.users"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="min-w-40">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >Entity</label
                    >
                    <Select v-model="entity">
                        <SelectTrigger><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All entities</SelectItem>
                            <SelectItem
                                v-for="option in props.entities"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="w-40">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >From</label
                    >
                    <DateInput v-model="from" placeholder="From" />
                </div>
                <div class="w-40">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >To</label
                    >
                    <DateInput v-model="to" placeholder="To" />
                </div>
                <Button type="button" @click="apply">Apply</Button>
            </div>

            <DataTable
                :data="props.rows"
                :columns="columns"
                :total="props.total"
                :page="props.currentPage"
                :per-page="props.perPage"
                empty-title="No audit entries"
            >
                <template #cell="{ row, column }">
                    <template v-if="column.key === 'createdAt'">
                        {{ formatDateTime(String(row.createdAt)) }}
                    </template>
                    <template v-else-if="column.key === 'causerName'">
                        {{ row.causerName ?? 'System' }}
                    </template>
                    <template v-else-if="column.key === 'subjectType'">
                        {{
                            row.subjectType
                                ? String(row.subjectType).split('\\').pop()
                                : '—'
                        }}
                    </template>
                    <template v-else-if="column.key === 'reason'">
                        {{ row.reason ?? '—' }}
                    </template>
                    <template v-else-if="column.key === 'changes'">
                        <span class="block max-w-md truncate text-xs">{{
                            changeSummary(row.changes)
                        }}</span>
                    </template>
                    <template v-else>{{ row[column.key] }}</template>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
