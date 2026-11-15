<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle } from '@lucide/vue';
import ForecastChart from '@/components/ForecastChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import { formatDate, formatMoney } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Forecast', href: '/forecast' }] },
});

type DueLine = {
    id: number;
    partyName: string;
    kindLabel: string;
    direction: 'in' | 'out';
    dueOn: string;
    remainingCents: number;
    overdue: boolean;
    description: string | null;
};

type Bucket = {
    index: number;
    label: string;
    from: string;
    to: string;
    inCents: number;
    outCents: number;
    balanceCents: number;
    belowPayroll: boolean;
    negative: boolean;
    dues: DueLine[];
};

type Scope = {
    label: string;
    officeId: number | null;
    startBalanceCents: number;
    endBalanceCents: number;
    lowestBalanceCents: number;
    firstNegativeBucket: string | null;
    nextPayrollCents: number | null;
    buckets: Bucket[];
    overdueIn: { count: number; cents: number; dues: DueLine[] };
    staleAccounts: Array<{
        id: number;
        name: string;
        officeName: string | null;
        lastReconciled: string | null;
    }>;
};

type Props = {
    days: number;
    includesPayroll: boolean;
    includeOverdueIn: boolean;
    company: Scope;
    offices: Scope[];
};

const props = defineProps<Props>();
const page = usePage();
const symbol = computed(() => page.props.settings.currencySymbol);
const open = ref<string | null>(null);

function money(cents: number): string {
    return formatMoney(cents, symbol.value);
}

function go(params: { days?: number; include_overdue?: boolean }) {
    router.get(
        '/forecast',
        {
            days: params.days ?? props.days,
            include_overdue:
                (params.include_overdue ?? props.includeOverdueIn)
                    ? 1
                    : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function points(scope: Scope) {
    return scope.buckets.map((bucket) => ({
        label: bucket.label,
        inflow: bucket.inCents,
        outflow: bucket.outCents,
        balance: bucket.balanceCents,
    }));
}

function toggle(scope: Scope, bucket: Bucket) {
    const key = `${scope.officeId ?? 'all'}-${bucket.index}`;

    open.value = open.value === key ? null : key;
}

function isOpen(scope: Scope, bucket: Bucket): boolean {
    return open.value === `${scope.officeId ?? 'all'}-${bucket.index}`;
}

const scopes = computed(() =>
    props.offices.length > 1
        ? [props.company, ...props.offices]
        : [props.company],
);
</script>

<template>
    <Head title="Forecast" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Forecast"
            description="Where cash is heading, week by week, from today's real account balances."
        >
            <template #actions>
                <div
                    class="flex rounded-md border"
                    role="group"
                    aria-label="Forecast length"
                >
                    <Button
                        v-for="option in [30, 60, 90]"
                        :key="option"
                        :variant="props.days === option ? 'default' : 'ghost'"
                        size="sm"
                        @click="go({ days: option })"
                    >
                        {{ option }} days
                    </Button>
                </div>
            </template>
        </PageHeader>

        <div class="flex flex-wrap items-center gap-4 text-sm">
            <div class="flex items-center gap-2">
                <Checkbox
                    id="include-overdue"
                    :model-value="props.includeOverdueIn"
                    @update:model-value="
                        (v) => go({ include_overdue: v === true })
                    "
                />
                <Label for="include-overdue" class="font-normal">
                    Also count overdue money in, as if collected on day one
                </Label>
            </div>
            <p v-if="!props.includesPayroll" class="text-muted-foreground">
                Payroll is not included in this view.
            </p>
        </div>

        <div
            class="grid gap-4"
            :class="scopes.length > 1 ? 'xl:grid-cols-2' : ''"
        >
            <Card v-for="scope in scopes" :key="scope.officeId ?? 'all'">
                <CardHeader>
                    <CardTitle>{{ scope.label }}</CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <StatCard
                            label="Now"
                            :value="money(scope.startBalanceCents)"
                        />
                        <StatCard
                            :label="`In ${props.days} days`"
                            :value="money(scope.endBalanceCents)"
                        />
                        <StatCard
                            label="Lowest point"
                            :value="money(scope.lowestBalanceCents)"
                        />
                    </div>

                    <p
                        v-if="scope.firstNegativeBucket"
                        class="flex items-center gap-2 rounded-md border border-danger-border bg-danger-soft p-3 text-sm text-danger"
                        role="alert"
                    >
                        <AlertTriangle class="size-4" />
                        The balance goes negative in
                        {{ scope.firstNegativeBucket }}.
                    </p>

                    <p
                        v-if="scope.staleAccounts.length > 0"
                        class="rounded-md border border-warning-border bg-warning-soft p-3 text-sm"
                        role="status"
                    >
                        These balances may be wrong, because the account has not
                        been reconciled for over 30 days:
                        <span
                            v-for="(account, index) in scope.staleAccounts"
                            :key="account.id"
                        >
                            <Link
                                :href="`/accounts/${account.id}`"
                                class="font-medium underline"
                                >{{ account.name }}</Link
                            >
                            <template v-if="account.officeName">
                                · {{ account.officeName }}</template
                            >
                            <template v-if="account.lastReconciled">
                                ({{
                                    formatDate(account.lastReconciled)
                                }})</template
                            >
                            <template v-else> (never)</template
                            ><template
                                v-if="index < scope.staleAccounts.length - 1"
                                >,
                            </template> </span
                        >.
                    </p>

                    <ForecastChart :points="points(scope)" />

                    <p
                        v-if="scope.nextPayrollCents !== null"
                        class="text-xs text-muted-foreground"
                    >
                        Next payroll run: {{ money(scope.nextPayrollCents) }}.
                        Weeks that end below it are marked.
                    </p>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr
                                    class="border-b text-left text-muted-foreground"
                                >
                                    <th class="px-2 py-2 font-medium">Week</th>
                                    <th
                                        class="px-2 py-2 text-right font-medium"
                                    >
                                        In
                                    </th>
                                    <th
                                        class="px-2 py-2 text-right font-medium"
                                    >
                                        Out
                                    </th>
                                    <th
                                        class="px-2 py-2 text-right font-medium"
                                    >
                                        Balance
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template
                                    v-for="bucket in scope.buckets"
                                    :key="bucket.index"
                                >
                                    <tr
                                        class="cursor-pointer border-b hover:bg-muted/50"
                                        :class="
                                            bucket.negative
                                                ? 'bg-danger-soft/60'
                                                : bucket.belowPayroll
                                                  ? 'bg-warning-soft/60'
                                                  : ''
                                        "
                                        :aria-expanded="isOpen(scope, bucket)"
                                        tabindex="0"
                                        @click="toggle(scope, bucket)"
                                        @keydown.enter="toggle(scope, bucket)"
                                    >
                                        <td class="px-2 py-2">
                                            <span class="font-medium">{{
                                                bucket.label
                                            }}</span>
                                            <span
                                                class="ml-2 text-xs text-muted-foreground"
                                            >
                                                {{ formatDate(bucket.from) }} –
                                                {{ formatDate(bucket.to) }}
                                            </span>
                                            <span
                                                v-if="
                                                    bucket.belowPayroll &&
                                                    !bucket.negative
                                                "
                                                class="ml-2 text-xs text-warning"
                                                >below next payroll</span
                                            >
                                        </td>
                                        <td
                                            class="px-2 py-2 text-right tabular-nums"
                                        >
                                            <MoneyText
                                                :cents="bucket.inCents"
                                                :symbol="symbol"
                                                direction="in"
                                            />
                                        </td>
                                        <td
                                            class="px-2 py-2 text-right tabular-nums"
                                        >
                                            <MoneyText
                                                :cents="bucket.outCents"
                                                :symbol="symbol"
                                                direction="out"
                                            />
                                        </td>
                                        <td
                                            class="px-2 py-2 text-right font-medium tabular-nums"
                                        >
                                            {{ money(bucket.balanceCents) }}
                                        </td>
                                    </tr>
                                    <tr v-if="isOpen(scope, bucket)">
                                        <td
                                            colspan="4"
                                            class="bg-muted/30 px-4 py-2"
                                        >
                                            <p
                                                v-if="bucket.dues.length === 0"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Nothing is due this week.
                                            </p>
                                            <ul
                                                v-else
                                                class="space-y-1 text-xs"
                                            >
                                                <li
                                                    v-for="due in bucket.dues"
                                                    :key="due.id"
                                                    class="flex justify-between gap-3"
                                                >
                                                    <span>
                                                        {{ due.partyName }} ·
                                                        {{ due.kindLabel }} ·
                                                        {{
                                                            formatDate(
                                                                due.dueOn,
                                                            )
                                                        }}
                                                        <span
                                                            v-if="due.overdue"
                                                            class="text-danger"
                                                            >(overdue)</span
                                                        >
                                                    </span>
                                                    <span
                                                        class="tabular-nums"
                                                        :class="
                                                            due.direction ===
                                                            'in'
                                                                ? 'text-success'
                                                                : 'text-danger'
                                                        "
                                                    >
                                                        {{
                                                            due.direction ===
                                                            'in'
                                                                ? '+'
                                                                : '−'
                                                        }}{{
                                                            money(
                                                                due.remainingCents,
                                                            )
                                                        }}
                                                    </span>
                                                </li>
                                            </ul>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div
                        v-if="scope.overdueIn.count > 0"
                        class="rounded-md border p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ scope.overdueIn.count }} overdue
                            {{
                                scope.overdueIn.count === 1
                                    ? 'receivable'
                                    : 'receivables'
                            }}:
                            {{ money(scope.overdueIn.cents) }}
                            <span class="font-normal text-muted-foreground">
                                {{
                                    props.includeOverdueIn
                                        ? '(counted above)'
                                        : '(not counted above)'
                                }}
                            </span>
                        </p>
                        <ul
                            class="mt-1 space-y-1 text-xs text-muted-foreground"
                        >
                            <li
                                v-for="due in scope.overdueIn.dues.slice(0, 8)"
                                :key="due.id"
                                class="flex justify-between gap-3"
                            >
                                <span
                                    >{{ due.partyName }} · due
                                    {{ formatDate(due.dueOn) }}</span
                                >
                                <span class="tabular-nums">{{
                                    money(due.remainingCents)
                                }}</span>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
