<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import SessionActions from '@/components/attendance/SessionActions.vue';
import SessionDialog from '@/components/attendance/SessionDialog.vue';
import DataTable from '@/components/kit/DataTable.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import type { DataTableColumn } from '@/components/kit/types';
import { formatDateTime } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Needs review', href: '/attendance/review' },
        ],
    },
});

type Props = {
    sessions: App.Data.AttendanceSessionData[];
    total: number;
    page: number;
    perPage: number;
    employees: App.Data.AttendanceEmployeeOptionData[];
};

const props = defineProps<Props>();

const columns: DataTableColumn[] = [
    { key: 'employeeName', label: 'Employee' },
    { key: 'startedAt', label: 'Started' },
    { key: 'endedAt', label: 'Ended' },
    { key: 'workedFormatted', label: 'Worked', align: 'right' },
    { key: 'reviewReasons', label: 'Why it needs review' },
    { key: 'actions', label: '', align: 'right' },
];

const dialogOpen = ref(false);
const editing = ref<App.Data.AttendanceSessionData | null>(null);

function openEdit(session: App.Data.AttendanceSessionData) {
    editing.value = session;
    dialogOpen.value = true;
}
</script>

<template>
    <Head title="Attendance needs review" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Needs review"
            description="Auto-closed sessions, sessions longer than 12 hours, and overlapping sessions. Approving takes a row out of this queue."
        />

        <AttendanceNav />

        <DataTable
            :data="props.sessions"
            :columns="columns"
            :total="props.total"
            :page="props.page"
            :per-page="props.perPage"
            empty-title="Nothing needs review"
            empty-description="Forgotten check-outs and unusual sessions will appear here."
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
                <template v-else-if="column.key === 'reviewReasons'">
                    <div class="flex flex-wrap gap-1">
                        <span
                            v-for="reason in row.reviewReasons as string[]"
                            :key="reason"
                            class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                        >
                            {{ reason }}
                        </span>
                    </div>
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
