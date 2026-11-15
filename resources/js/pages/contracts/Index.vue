<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import ContractFormDialog from '@/components/contracts/ContractFormDialog.vue';
import ContractStatusActions from '@/components/contracts/ContractStatusActions.vue';
import DataTable from '@/components/kit/DataTable.vue';
import FilterBar from '@/components/kit/FilterBar.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import ExportButton from '@/components/kit/ExportButton.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import type { DataTableColumn, SelectFilter } from '@/components/kit/types';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Contracts', href: '/contracts' }] },
});

type Props = {
    contracts: App.Data.ContractData[];
    total: number;
    page: number;
    perPage: number;
    sort: string;
    direction: 'asc' | 'desc';
    filters: App.Data.ContractFiltersData;
    clients: App.Data.ContractClientOptionData[];
    offices: App.Data.OfficeOptionData[];
    statuses: Array<{ value: string; label: string }>;
    cycles: Array<{ value: string; label: string }>;
    canChooseOffice: boolean;
    types: Array<{ value: string; label: string }>;
    suppliers: Array<{ id: number; name: string }>;
    categories: Array<{ id: number; name: string }>;
};

const props = defineProps<Props>();
const page = usePage();

const columns: DataTableColumn[] = [
    { key: 'client', label: 'Client' },
    { key: 'amount_cents', label: 'Amount', sortable: true, align: 'right' },
    { key: 'cycleLabel', label: 'Cycle' },
    { key: 'starts_on', label: 'Period', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'actions', label: '', align: 'right' },
];

const statusFilter = computed<SelectFilter>(() => ({
    key: 'status',
    label: 'Status',
    value: props.filters.status || null,
    options: props.statuses.map((status) => ({
        value: status.value,
        label: status.label,
    })),
}));

const dialogOpen = ref(false);
const editing = ref<App.Data.ContractData | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(contract: App.Data.ContractData) {
    editing.value = contract;
    dialogOpen.value = true;
}
</script>

<template>
    <Head title="Contracts" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Contracts"
            description="SaaS renewals, their cycles and their current state."
        >
            <template #actions>
                <ExportButton href="/contracts/export" />
                <Button
                    :disabled="props.clients.length === 0"
                    @click="openCreate"
                >
                    <Plus class="mr-1 size-4" /> New contract
                </Button>
            </template>
        </PageHeader>

        <p
            v-if="props.clients.length === 0"
            class="text-sm text-muted-foreground"
        >
            Add a client before creating a contract.
        </p>

        <FilterBar
            :search="props.filters.q"
            search-placeholder="Search by client name"
            :select="statusFilter"
        />

        <DataTable
            :data="props.contracts"
            :columns="columns"
            :total="props.total"
            :page="props.page"
            :per-page="props.perPage"
            :sort="props.sort"
            :direction="props.direction"
            empty-title="No contracts yet"
            empty-description="Create the first SaaS renewal for a client."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'client'">
                    <Link
                        :href="`/contracts/${row.id}`"
                        class="font-medium hover:underline"
                    >
                        {{ row.partyName }}
                    </Link>
                    <p class="text-xs text-muted-foreground">
                        {{ row.officeName }}
                    </p>
                </template>
                <template v-else-if="column.key === 'amount_cents'">
                    <MoneyText
                        :cents="Number(row.amountCents)"
                        :symbol="page.props.settings.currencySymbol"
                        direction="in"
                    />
                </template>
                <template v-else-if="column.key === 'starts_on'">
                    {{ formatDate(String(row.startsOn)) }}
                    <span class="text-muted-foreground">
                        –
                        {{
                            row.endsOn
                                ? formatDate(String(row.endsOn))
                                : 'ongoing'
                        }}
                    </span>
                </template>
                <template v-else-if="column.key === 'status'">
                    <StatusBadge
                        :status="String(row.status)"
                        :labels="{
                            [String(row.status)]: String(row.statusLabel),
                        }"
                    />
                </template>
                <template v-else-if="column.key === 'actions'">
                    <div class="flex flex-wrap justify-end gap-1">
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="`/contracts/${row.id}`">View</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEdit(row as App.Data.ContractData)"
                        >
                            Edit
                        </Button>
                        <ContractStatusActions
                            :contract-id="Number(row.id)"
                            :status="row.status as App.Enums.ContractStatus"
                        />
                    </div>
                </template>
                <template v-else>{{ row[column.key] }}</template>
            </template>
        </DataTable>
    </div>

    <ContractFormDialog
        v-model:open="dialogOpen"
        :clients="props.clients"
        :offices="props.offices"
        :cycles="props.cycles"
        :can-choose-office="props.canChooseOffice"
        :types="props.types"
        :suppliers="props.suppliers"
        :categories="props.categories"
        :contract="editing"
    />
</template>
