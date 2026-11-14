<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import DueRow from '@/components/dues/DueRow.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import FilterBar from '@/components/kit/FilterBar.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import type { FilterTab } from '@/components/kit/types';
import TriageFilters from '@/components/triage/TriageFilters.vue';
import { formatDate } from '@/lib/format';

defineOptions({ layout: { breadcrumbs: [{ title: 'Triage', href: '/' }] } });

type Option = { value: string; label: string };

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    filters: App.Data.TriageFiltersData;
    tabs: App.Data.TriageTabData[];
    dues: Paginated<App.Data.DueRowData> | null;
    attention: App.Data.AttentionContractData[];
    weekEnd: string;
    clients: App.Data.ContractClientOptionData[];
    officeOptions: App.Data.OfficeOptionData[];
    assignees: App.Data.AccountManagerOptionData[];
    kinds: Option[];
    directions: Option[];
};

const props = defineProps<Props>();
const page = usePage();

const filterTabs = computed<FilterTab[]>(() =>
    props.tabs.map((tab) => ({
        value: tab.value,
        label: tab.label,
        count: tab.count,
    })),
);

const active = computed(
    () =>
        props.tabs.find((tab) => tab.value === props.filters.tab) ??
        props.tabs[0],
);

const hints: Record<string, string> = {
    overdue: 'Due before today and still open.',
    today: 'Due today.',
    month: 'Due after this week, up to the end of the month.',
    attention: 'Active contracts with no further due scheduled.',
};

const hint = computed(() =>
    active.value.value === 'week'
        ? `Due after today, up to ${formatDate(props.weekEnd)} (the end of the week).`
        : (hints[active.value.value] ?? ''),
);
</script>

<template>
    <Head title="Triage" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Triage"
            description="Every open due, by how soon it needs you."
        />

        <FilterBar
            :tabs="filterTabs"
            :active-tab="props.filters.tab"
            :searchable="false"
        />

        <TriageFilters
            :filters="props.filters"
            :kinds="props.kinds"
            :directions="props.directions"
            :clients="props.clients"
            :offices="props.officeOptions"
            :assignees="props.assignees"
        />

        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <p class="text-sm text-muted-foreground">{{ hint }}</p>
            <p
                v-if="active.collectCents !== null"
                class="flex flex-wrap items-baseline gap-x-3 text-sm"
            >
                <span>{{ active.count }} open</span>
                <span v-if="active.collectCents > 0">
                    To collect
                    <MoneyText
                        :cents="active.collectCents"
                        :symbol="page.props.settings.currencySymbol"
                        direction="in"
                    />
                </span>
                <span v-if="active.payCents !== null && active.payCents > 0">
                    To pay
                    <MoneyText
                        :cents="active.payCents"
                        :symbol="page.props.settings.currencySymbol"
                        direction="out"
                    />
                </span>
            </p>
        </div>

        <Card v-if="props.filters.tab === 'attention'">
            <CardContent v-if="props.attention.length > 0" class="p-0">
                <div class="divide-y">
                    <Link
                        v-for="contract in props.attention"
                        :key="contract.id"
                        :href="`/contracts/${contract.id}`"
                        class="flex flex-col gap-1 p-4 hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ contract.clientName }} ·
                                {{ contract.cycleLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ contract.reason }}
                                <template v-if="contract.endsOn">
                                    Ended
                                    {{ formatDate(contract.endsOn) }}.
                                </template>
                            </p>
                        </div>
                        <MoneyText
                            :cents="contract.amountCents"
                            :symbol="page.props.settings.currencySymbol"
                            direction="in"
                        />
                    </Link>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="Nothing needs attention"
                    description="Every active contract has a due coming up."
                />
            </CardContent>
        </Card>

        <Card v-else-if="props.dues">
            <CardContent v-if="props.dues.data.length > 0" class="p-0">
                <div class="divide-y">
                    <DueRow
                        v-for="due in props.dues.data"
                        :key="due.id"
                        :due="due"
                        :symbol="page.props.settings.currencySymbol"
                        show-client
                        :show-cancel="false"
                    />
                </div>
                <div
                    v-if="props.dues.last_page > 1"
                    class="flex items-center justify-between border-t p-3 text-sm"
                >
                    <span class="text-muted-foreground">
                        Page {{ props.dues.current_page }} of
                        {{ props.dues.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Button
                            v-if="props.dues.prev_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="props.dues.prev_page_url"
                                preserve-scroll
                            >
                                Previous
                            </Link>
                        </Button>
                        <Button
                            v-if="props.dues.next_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="props.dues.next_page_url"
                                preserve-scroll
                            >
                                Next
                            </Link>
                        </Button>
                    </div>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="Nothing here"
                    description="No open dues match this tab and these filters."
                />
            </CardContent>
        </Card>
    </div>
</template>
