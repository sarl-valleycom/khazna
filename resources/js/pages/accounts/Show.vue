<script setup lang="ts">
import { ref, watchEffect } from 'vue';
import { Head, Link, router, usePage, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import AccountFormDialog from '@/components/accounts/AccountFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import ExportButton from '@/components/kit/ExportButton.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Accounts', href: '/accounts' }] },
});

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    account: App.Data.AccountRowData;
    entries: Paginated<App.Data.AccountStatementRowData>;
    filters: { from: string | null; to: string | null };
    summary: {
        startCents: number;
        startFormatted: string;
        totalInFormatted: string;
        totalOutFormatted: string;
        endCents: number;
        endFormatted: string;
    };
    types: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const page = usePage();

// The static layout breadcrumb only names the section; add the record being viewed.
watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: 'Accounts', href: '/accounts' },
            { title: props.account.name, href: page.url },
        ],
    }),
);

const from = ref<string | null>(props.filters.from);
const to = ref<string | null>(props.filters.to);
const editOpen = ref(false);

function applyFilters() {
    router.get(
        `/accounts/${props.account.id}`,
        { from: from.value || undefined, to: to.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function clearFilters() {
    from.value = null;
    to.value = null;
    applyFilters();
}
</script>

<template>
    <Head :title="props.account.name" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            :title="props.account.name"
            :description="`${props.account.officeName} · ${props.account.typeLabel}`"
        >
            <template #actions>
                <ExportButton
                    :href="`/accounts/${props.account.id}/statement/export`"
                />
                <ExportButton
                    :href="`/accounts/${props.account.id}/statement`"
                    pdf
                    label="Statement PDF"
                />
                <Button variant="outline" as-child>
                    <Link href="/accounts">
                        <ArrowLeft class="mr-1 size-4" /> All accounts
                    </Link>
                </Button>
                <Button
                    v-if="props.account.canEdit"
                    variant="outline"
                    @click="editOpen = true"
                >
                    Edit
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard
                label="Current balance"
                :value="props.account.balanceFormatted"
            />
            <StatCard
                label="Opening (range start)"
                :value="props.summary.startFormatted"
            />
            <StatCard label="In" :value="props.summary.totalInFormatted" />
            <StatCard label="Out" :value="props.summary.totalOutFormatted" />
        </div>

        <Card>
            <CardHeader
                class="gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <CardTitle>Statement</CardTitle>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="w-36">
                        <DateInput
                            v-model="from"
                            placeholder="From"
                            :max="to ?? undefined"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <span class="text-muted-foreground">–</span>
                    <div class="w-36">
                        <DateInput
                            v-model="to"
                            placeholder="To"
                            :min="from ?? undefined"
                            @update:model-value="applyFilters"
                        />
                    </div>
                    <Button
                        v-if="from || to"
                        variant="ghost"
                        size="sm"
                        @click="clearFilters"
                    >
                        Clear
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <EmptyState
                    v-if="props.entries.data.length === 0"
                    title="No entries"
                    description="Entries appear here when payments are recorded into this account."
                />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="px-4 py-2 font-medium">Date</th>
                                <th class="px-4 py-2 font-medium">
                                    Description
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    In
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Out
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Balance
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in props.entries.data"
                                :key="entry.id"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-2 whitespace-nowrap">
                                    {{ formatDate(entry.entryDate) }}
                                </td>
                                <td class="px-4 py-2">
                                    {{ entry.description }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right text-emerald-700 tabular-nums dark:text-emerald-400"
                                >
                                    {{ entry.inFormatted }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right text-red-700 tabular-nums dark:text-red-400"
                                >
                                    {{ entry.outFormatted }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-medium tabular-nums"
                                >
                                    {{ entry.runningFormatted }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    v-if="props.entries.last_page > 1"
                    class="flex items-center justify-between border-t p-3 text-sm"
                >
                    <span class="text-muted-foreground">
                        Page {{ props.entries.current_page }} of
                        {{ props.entries.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Button
                            v-if="props.entries.prev_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="props.entries.prev_page_url"
                                preserve-scroll
                                >Previous</Link
                            >
                        </Button>
                        <Button
                            v-if="props.entries.next_page_url"
                            variant="outline"
                            size="sm"
                            as-child
                        >
                            <Link
                                :href="props.entries.next_page_url"
                                preserve-scroll
                                >Next</Link
                            >
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <p class="text-sm text-muted-foreground">
            Balance at the end of the range:
            <MoneyText
                :cents="props.summary.endCents"
                :symbol="page.props.settings.currencySymbol"
            />. Excel export arrives in a later phase.
        </p>
    </div>

    <AccountFormDialog
        v-model:open="editOpen"
        :offices="[]"
        :types="props.types"
        :account="props.account"
    />
</template>
