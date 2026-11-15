<script setup lang="ts">
import { Deferred, Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarClock,
    HandCoins,
    Repeat,
    Wallet,
} from '@lucide/vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EmptyState from '@/components/kit/EmptyState.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import { formatMoney } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }] },
});

type Kpis = {
    collectedCents: number;
    outstandingCents: number;
    overdueCents: number;
    mrrCents: number;
    renewals: { count: number; cents: number };
};

type OfficeBalances = {
    officeId: number;
    officeName: string;
    totalCents: number;
    accounts: Array<{
        id: number;
        name: string;
        typeLabel: string;
        balanceCents: number;
        active: boolean;
    }>;
};

type Props = {
    officeId: number | null;
    monthLabel: string;
    kpis?: Kpis;
    balances?: OfficeBalances[];
    salary?: { billCents: number | null };
    ledgerCheck?: {
        checkedAt: string | null;
        total: number;
        problems: string[];
    } | null;
};

const props = defineProps<Props>();
const page = usePage();

function money(cents: number): string {
    return formatMoney(cents, page.props.settings.currencySymbol);
}
</script>

<template>
    <Head title="Dashboard" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Dashboard"
            :description="`${props.monthLabel}${props.officeId ? ' · one office' : ' · all offices'}. Use the office switcher to narrow every card.`"
        />

        <div
            v-if="props.ledgerCheck && props.ledgerCheck.total > 0"
            class="rounded-md border border-danger-border bg-danger-soft p-4 text-sm text-danger"
            role="alert"
        >
            <p class="font-medium">
                The daily ledger check found
                {{ props.ledgerCheck.problems.length }}
                {{
                    props.ledgerCheck.problems.length === 1
                        ? 'problem'
                        : 'problems'
                }}.
            </p>
            <ul class="mt-1 list-disc pl-5">
                <li
                    v-for="problem in props.ledgerCheck.problems.slice(0, 5)"
                    :key="problem"
                >
                    {{ problem }}
                </li>
            </ul>
        </div>

        <Deferred data="kpis">
            <template #fallback>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <StatCard
                        v-for="label in [
                            'Collected this month',
                            'Outstanding',
                            'Overdue',
                            'MRR',
                            'Renewals in 30 days',
                        ]"
                        :key="label"
                        :label="label"
                        value="—"
                        loading
                    />
                </div>
            </template>
            <div
                v-if="props.kpis"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                <StatCard
                    label="Collected this month"
                    :value="money(props.kpis.collectedCents)"
                    :icon="Wallet"
                    tone="success"
                />
                <StatCard
                    label="Outstanding"
                    :value="money(props.kpis.outstandingCents)"
                    :icon="HandCoins"
                    tone="warning"
                />
                <Link href="/?tab=overdue" class="block">
                    <StatCard
                        label="Overdue"
                        :value="money(props.kpis.overdueCents)"
                        :icon="AlertTriangle"
                        :tone="
                            props.kpis.overdueCents > 0 ? 'danger' : 'neutral'
                        "
                    />
                </Link>
                <StatCard
                    label="MRR"
                    :value="money(props.kpis.mrrCents)"
                    :icon="Repeat"
                    tone="info"
                />
                <StatCard
                    label="Renewals in 30 days"
                    :value="props.kpis.renewals.count"
                    :hint="money(props.kpis.renewals.cents) + ' to collect'"
                    :icon="CalendarClock"
                    tone="info"
                />
            </div>
        </Deferred>

        <Deferred v-if="'salary' in props" data="salary">
            <template #fallback>
                <StatCard label="Salary bill" value="—" loading />
            </template>
            <div
                v-if="props.salary?.billCents != null"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                <StatCard
                    label="Salary bill (per month)"
                    :value="money(props.salary.billCents)"
                />
            </div>
        </Deferred>

        <Deferred data="balances">
            <template #fallback>
                <StatCard label="Account balances" value="—" loading />
            </template>
            <template v-if="props.balances && props.balances.length > 0">
                <Card v-for="office in props.balances" :key="office.officeId">
                    <CardHeader
                        class="flex flex-row items-center justify-between"
                    >
                        <CardTitle>{{ office.officeName }}</CardTitle>
                        <MoneyText
                            :cents="office.totalCents"
                            :symbol="page.props.settings.currencySymbol"
                        />
                    </CardHeader>
                    <CardContent class="p-0">
                        <div class="divide-y">
                            <Link
                                v-for="account in office.accounts"
                                :key="account.id"
                                :href="`/accounts/${account.id}`"
                                class="flex items-center justify-between gap-3 p-4 hover:bg-muted/50"
                            >
                                <div>
                                    <p class="font-medium">
                                        {{ account.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ account.typeLabel }}
                                    </p>
                                </div>
                                <MoneyText
                                    :cents="account.balanceCents"
                                    :symbol="page.props.settings.currencySymbol"
                                />
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </template>
            <Card v-else-if="props.balances">
                <CardContent class="p-0">
                    <EmptyState
                        title="No account balances to show"
                        description="Balances are shown to the owner and finance."
                    />
                </CardContent>
            </Card>
        </Deferred>
    </div>
</template>
