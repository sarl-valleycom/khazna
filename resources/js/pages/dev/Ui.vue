<script setup lang="ts">
import { computed, ref } from 'vue';
import { Deferred, Head } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import UserText from '@/components/UserText.vue';
import AccountSelect from '@/components/kit/AccountSelect.vue';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import DataTable from '@/components/kit/DataTable.vue';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import FileInput from '@/components/kit/FileInput.vue';
import FilterBar from '@/components/kit/FilterBar.vue';
import type {
    DataTableColumn,
    DateRange,
    FilterTab,
} from '@/components/kit/types';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import OfficeSwitcher from '@/components/kit/OfficeSwitcher.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate, formatInteger, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'UI kit', href: '/dev/ui' },
        ],
    },
});

const props = defineProps<{
    page: App.Data.DevUiData;
    generatedAt?: string;
}>();

function asRow(row: Record<string, unknown>): App.Data.DemoDueRowData {
    return row as unknown as App.Data.DemoDueRowData;
}

const rows = computed<Record<string, unknown>[]>(() =>
    props.page.rows.map((row) => ({ ...row })),
);

const tableDirection = computed<'asc' | 'desc'>(() =>
    props.page.direction === 'desc' ? 'desc' : 'asc',
);

const amount = ref<number | null>(props.page.amountCents);
const date = ref<string | null>(props.page.date);
const accountId = ref<number | null>(1);
const office = ref<string | null>(props.page.officeFilter);

const formOpen = ref(false);
const formProcessing = ref(false);
const formName = ref('SARL El Nour');

const confirmOpen = ref(false);

const tableLoading = ref(false);

const tabs = computed<FilterTab[]>(() => [
    { value: 'all', label: 'All', count: props.page.tabCounts.all },
    { value: 'in', label: 'Incoming', count: props.page.tabCounts.in },
    { value: 'out', label: 'Outgoing', count: props.page.tabCounts.out },
    { value: 'paid', label: 'Paid', count: props.page.tabCounts.paid },
]);

const statusFilter = computed(() => ({
    key: 'status',
    label: 'Status',
    value: props.page.statusFilter,
    options: Object.entries(props.page.statuses).map(([value, label]) => ({
        value,
        label,
    })),
}));

const dateRange = computed<DateRange>(() => ({
    from: props.page.from,
    to: props.page.to,
}));

const columns: DataTableColumn[] = [
    { key: 'client', label: 'Client', sortable: true },
    { key: 'clientArabic', label: 'Arabic' },
    { key: 'dueOn', label: 'Due', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'amountCents', label: 'Amount', sortable: true, align: 'right' },
];

function simulateFormSubmit() {
    formProcessing.value = true;

    window.setTimeout(() => {
        formProcessing.value = false;
        formOpen.value = false;
        toast.success('Demo form saved.');
    }, 600);
}

function confirmDemo() {
    confirmOpen.value = false;
    toast.success('Demo action confirmed.');
}

function fileError(message: string) {
    toast.error(message);
}
</script>

<template>
    <Head title="UI kit" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="props.page.title"
            description="Every shared component, in light and dark mode, on desktop and a 375 px phone."
        >
            <template #actions>
                <AppearanceTabs />
            </template>
        </PageHeader>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">1. Money, dates and user text</h2>
            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Arabic name</p>
                    <p class="mt-1 text-lg font-medium">
                        <UserText :text="props.page.clientNameArabic" />
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Money text</p>
                    <p class="mt-1 text-lg">
                        <MoneyText
                            :cents="props.page.amountCents"
                            :formatted="props.page.amountFormatted"
                            direction="in"
                            show-sign
                        />
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Date</p>
                    <p class="mt-1 text-lg font-medium">
                        {{ formatDate(props.page.date) }}
                    </p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="money-demo">Money input</Label>
                    <MoneyInput id="money-demo" v-model="amount" />
                    <p class="text-xs text-muted-foreground">
                        Integer centimes: {{ amount ?? 'null' }} ·
                        {{ formatMoney(amount ?? 0) }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label>Date input</Label>
                    <DateInput v-model="date" />
                    <p class="text-xs text-muted-foreground">
                        YYYY-MM-DD: {{ date ?? 'null' }}
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">2. Stats and statuses</h2>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Collected this month"
                    :value="formatMoney(12900050)"
                    :delta="8.4"
                    hint="vs last month"
                />
                <StatCard
                    label="Outstanding"
                    :value="formatMoney(5450000)"
                    :delta="-2.1"
                    hint="vs last month"
                />
                <StatCard
                    label="Clients"
                    :value="formatInteger(128)"
                    :delta="0"
                    hint="no change"
                />
                <StatCard label="Deferred prop" value="—" loading />
            </div>
            <div class="flex flex-wrap gap-2">
                <StatusBadge status="pending" :labels="props.page.statuses" />
                <StatusBadge status="partial" :labels="props.page.statuses" />
                <StatusBadge status="paid" :labels="props.page.statuses" />
                <StatusBadge status="canceled" :labels="props.page.statuses" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">
                3. Filters, offices and accounts
            </h2>
            <FilterBar
                :tabs="tabs"
                :active-tab="props.page.activeTab"
                :search="props.page.search"
                search-placeholder="Search client, Arabic name or phone"
                :select="statusFilter"
                :date-range="dateRange"
                :reload-props="['page']"
            />
            <div class="grid gap-3 md:grid-cols-2">
                <div class="space-y-2">
                    <Label>Office switcher</Label>
                    <OfficeSwitcher
                        v-model="office"
                        :offices="props.page.offices"
                        :reload-props="['page']"
                    />
                    <p class="text-xs text-muted-foreground">
                        Hidden for an office-restricted user. Current:
                        {{ office ?? 'all' }}
                    </p>
                </div>
                <div class="space-y-2">
                    <Label>Account select</Label>
                    <AccountSelect
                        v-model="accountId"
                        :accounts="props.page.accounts"
                    />
                    <p class="text-xs text-muted-foreground">
                        Selected account: {{ accountId ?? 'all' }}
                    </p>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">4. Table</h2>
                <label
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <input
                        v-model="tableLoading"
                        type="checkbox"
                        class="size-4"
                    />
                    Show loading state
                </label>
            </div>
            <DataTable
                :data="rows"
                :columns="columns"
                :total="props.page.total"
                :page="props.page.currentPage"
                :per-page="props.page.perPage"
                :sort="props.page.sort"
                :direction="tableDirection"
                :loading="tableLoading"
                :empty-title="'No dues match these filters'"
                empty-description="Clear the search or pick another status."
                :reload-props="['page']"
            >
                <template #cell="{ row, column }">
                    <UserText
                        v-if="
                            column.key === 'client' ||
                            column.key === 'clientArabic'
                        "
                        :text="String(row[column.key])"
                    />
                    <span v-else-if="column.key === 'dueOn'">
                        {{ formatDate(asRow(row).dueOn) }}
                    </span>
                    <StatusBadge
                        v-else-if="column.key === 'status'"
                        :status="asRow(row).status"
                        :labels="props.page.statuses"
                    />
                    <MoneyText
                        v-else-if="column.key === 'amountCents'"
                        :cents="asRow(row).amountCents"
                        :formatted="asRow(row).amountFormatted"
                        :direction="
                            asRow(row).direction === 'in' ? 'in' : 'out'
                        "
                    />
                    <span v-else>{{ row[column.key] }}</span>
                </template>
            </DataTable>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-semibold">
                5. Dialogs, files and empty states
            </h2>
            <div class="flex flex-wrap gap-2">
                <button
                    type="button"
                    class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    @click="formOpen = true"
                >
                    Open form dialog
                </button>
                <button
                    type="button"
                    class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    @click="confirmOpen = true"
                >
                    Open confirm dialog
                </button>
                <button
                    type="button"
                    class="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                    @click="
                        toast.success(
                            'Toast component is wired to flash messages.',
                        )
                    "
                >
                    Show toast
                </button>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <FileInput
                    accept="image/*,.pdf"
                    :max-size-mb="2"
                    @invalid="fileError"
                />
                <EmptyState
                    title="No dues yet"
                    description="Create the first due to see it here."
                >
                    <template #action>
                        <button
                            type="button"
                            class="rounded-md bg-primary px-3 py-2 text-sm text-primary-foreground"
                            @click="toast.info('Empty state action clicked.')"
                        >
                            Add due
                        </button>
                    </template>
                </EmptyState>
            </div>
        </section>

        <Deferred data="generatedAt">
            <template #fallback>
                <p class="text-sm text-muted-foreground">
                    Loading deferred prop…
                </p>
            </template>
            <p class="text-sm text-muted-foreground">
                Deferred prop loaded at {{ props.generatedAt }}
            </p>
        </Deferred>

        <FormDialog
            v-model:open="formOpen"
            title="Edit client"
            description="A dialog wrapping an Inertia-style form with a pending state."
            :processing="formProcessing"
            submit-label="Save client"
            @submit="simulateFormSubmit"
        >
            <div class="space-y-2">
                <Label for="demo-client-name">Client name</Label>
                <Input id="demo-client-name" v-model="formName" />
            </div>
        </FormDialog>

        <ConfirmDialog
            v-model:open="confirmOpen"
            title="Delete this client?"
            description="This is a demo. Nothing is deleted."
            confirm-label="Delete client"
            reason-required
            destructive
            @confirm="confirmDemo"
        />
    </div>
</template>
