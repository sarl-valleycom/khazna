<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import SessionActions from '@/components/attendance/SessionActions.vue';
import SessionDialog from '@/components/attendance/SessionDialog.vue';
import DataTable from '@/components/kit/DataTable.vue';
import DateInput from '@/components/kit/DateInput.vue';
import ExportButton from '@/components/kit/ExportButton.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import type { DataTableColumn } from '@/components/kit/types';
import { formatDateTime } from '@/lib/format';
import { updateQuery } from '@/lib/query';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Sessions', href: '/attendance/sessions' },
        ],
    },
});

type Props = {
    sessions: App.Data.AttendanceSessionData[];
    total: number;
    page: number;
    perPage: number;
    sort: string;
    direction: 'asc' | 'desc';
    filters: App.Data.AttendanceFiltersData;
    employees: App.Data.AttendanceEmployeeOptionData[];
    offices: App.Data.OfficeOptionData[];
    statuses: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'employeeName', label: 'Employee', sortable: true },
    { key: 'startedAt', label: 'Started', sortable: true },
    { key: 'endedAt', label: 'Ended' },
    { key: 'workedFormatted', label: 'Worked', align: 'right' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'reviewStatus', label: 'Review' },
    { key: 'sourceLabel', label: 'Source' },
    { key: 'actions', label: '', align: 'right' },
];

const dialogOpen = ref(false);
const editing = ref<App.Data.AttendanceSessionData | null>(null);

const q = ref(props.filters.q);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

let typing = false;

watch(
    () => props.filters.q,
    (value) => {
        if (!typing) {
            q.value = value;
        }
    },
);

watch(
    () => props.filters.from,
    (value) => (from.value = value),
);

watch(
    () => props.filters.to,
    (value) => (to.value = value),
);

const sendSearch = useDebounceFn(() => {
    typing = false;
    updateQuery({ q: q.value || null, page: null });
}, 350);

function commitSearch() {
    typing = true;
    sendSearch();
}

function choose(key: string, value: string) {
    updateQuery({ [key]: value === 'all' ? null : value, page: null });
}

function commitDates() {
    updateQuery({ from: from.value || null, to: to.value || null, page: null });
}

const hasFilters = computed(
    () =>
        props.filters.q !== '' ||
        props.filters.employee !== null ||
        props.filters.office !== null ||
        props.filters.status !== '' ||
        props.filters.from !== null ||
        props.filters.to !== null,
);

function clearFilters() {
    q.value = '';
    from.value = null;
    to.value = null;

    updateQuery({
        q: null,
        employee: null,
        office: null,
        status: null,
        from: null,
        to: null,
        page: null,
    });
}

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(session: App.Data.AttendanceSessionData) {
    editing.value = session;
    dialogOpen.value = true;
}

function statusTone(status: string): string {
    return status === 'open'
        ? 'pending'
        : status === 'auto_closed'
          ? 'warning'
          : 'paid';
}

function reviewTone(status: string): string {
    return status === 'approved'
        ? 'paid'
        : status === 'needs_review'
          ? 'warning'
          : 'active';
}
</script>

<template>
    <Head title="Attendance sessions" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Attendance"
            description="Every recorded session. Manual changes need a reason and are audited."
        >
            <template #actions>
                <ExportButton href="/attendance/sessions/export" />
                <Button @click="openCreate">Add session</Button>
            </template>
        </PageHeader>

        <AttendanceNav />

        <div
            class="flex flex-col gap-3 rounded-xl border bg-card p-3 xl:flex-row xl:items-end"
        >
            <div class="min-w-52 flex-1">
                <label class="mb-1 block text-xs text-muted-foreground"
                    >Search</label
                >
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="q"
                        placeholder="Search by employee"
                        class="pl-8"
                        @input="commitSearch"
                    />
                </div>
            </div>

            <div class="w-full sm:w-52">
                <label class="mb-1 block text-xs text-muted-foreground"
                    >Employee</label
                >
                <Select
                    :model-value="
                        props.filters.employee === null
                            ? 'all'
                            : String(props.filters.employee)
                    "
                    @update:model-value="
                        (value) => choose('employee', String(value))
                    "
                >
                    <SelectTrigger aria-label="Employee">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All employees</SelectItem>
                        <SelectItem
                            v-for="employee in props.employees"
                            :key="employee.id"
                            :value="String(employee.id)"
                        >
                            {{ employee.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="w-full sm:w-44">
                <label class="mb-1 block text-xs text-muted-foreground"
                    >Office</label
                >
                <Select
                    :model-value="
                        props.filters.office === null
                            ? 'all'
                            : String(props.filters.office)
                    "
                    @update:model-value="
                        (value) => choose('office', String(value))
                    "
                >
                    <SelectTrigger aria-label="Office">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All offices</SelectItem>
                        <SelectItem
                            v-for="office in props.offices"
                            :key="office.id"
                            :value="String(office.id)"
                        >
                            {{ office.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="w-full sm:w-44">
                <label class="mb-1 block text-xs text-muted-foreground"
                    >Status</label
                >
                <Select
                    :model-value="props.filters.status || 'all'"
                    @update:model-value="
                        (value) => choose('status', String(value))
                    "
                >
                    <SelectTrigger aria-label="Status">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All statuses</SelectItem>
                        <SelectItem
                            v-for="status in props.statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="flex items-center gap-2">
                <div class="w-36">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >From</label
                    >
                    <DateInput
                        v-model="from"
                        placeholder="From"
                        :max="to ?? undefined"
                        @update:model-value="commitDates"
                    />
                </div>
                <span class="mt-5 text-muted-foreground">–</span>
                <div class="w-36">
                    <label class="mb-1 block text-xs text-muted-foreground"
                        >To</label
                    >
                    <DateInput
                        v-model="to"
                        placeholder="To"
                        :min="from ?? undefined"
                        @update:model-value="commitDates"
                    />
                </div>
            </div>

            <Button
                v-if="hasFilters"
                type="button"
                variant="ghost"
                @click="clearFilters"
            >
                Clear
            </Button>
        </div>

        <DataTable
            :data="props.sessions"
            :columns="columns"
            :total="props.total"
            :page="props.page"
            :per-page="props.perPage"
            :sort="props.sort"
            :direction="props.direction"
            empty-title="No sessions found"
            empty-description="Try another date range, or add a session manually."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'employeeName'">
                    <p class="font-medium">{{ row.employeeName }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ row.officeName }}
                    </p>
                </template>
                <template v-else-if="column.key === 'startedAt'">
                    {{ formatDateTime(String(row.startedAt)) }}
                </template>
                <template v-else-if="column.key === 'endedAt'">
                    {{
                        row.endedAt
                            ? formatDateTime(String(row.endedAt))
                            : 'Still open'
                    }}
                </template>
                <template v-else-if="column.key === 'status'">
                    <StatusBadge
                        :status="statusTone(String(row.status))"
                        :labels="{
                            pending: 'Open',
                            warning: 'Auto-closed',
                            paid: 'Closed',
                        }"
                    />
                </template>
                <template v-else-if="column.key === 'reviewStatus'">
                    <StatusBadge
                        :status="reviewTone(String(row.reviewStatus))"
                        :labels="{
                            active: 'OK',
                            warning: 'Needs review',
                            paid: 'Approved',
                        }"
                    />
                </template>
                <template v-else-if="column.key === 'actions'">
                    <SessionActions
                        :session="row as App.Data.AttendanceSessionData"
                        @edit="openEdit(row as App.Data.AttendanceSessionData)"
                    />
                </template>
                <template v-else>{{ row[column.key] }}</template>
            </template>
        </DataTable>
    </div>

    <SessionDialog
        v-model:open="dialogOpen"
        :employees="props.employees"
        :session="editing"
    />
</template>
