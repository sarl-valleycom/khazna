<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DateInput from '@/components/kit/DateInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import { formatMoney } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Cash report', href: '/reports/cash' }] },
});

type Row = {
    direction: 'in' | 'out';
    category: string;
    months: Record<string, number>;
    totalCents: number;
};

type Props = {
    includesPayroll: boolean;
    from: string;
    to: string;
    months: string[];
    rows: Row[];
    totals: {
        in: Record<string, number>;
        out: Record<string, number>;
        result: Record<string, number>;
        inCents: number;
        outCents: number;
        resultCents: number;
    };
    offices: Array<{
        officeId: number;
        officeName: string;
        inCents: number;
        outCents: number;
        resultCents: number;
        balanceCents: number;
        sentCents: number;
        receivedCents: number;
    }>;
    filters: { office: number | null; account: number | null };
    accounts: App.Data.PaymentAccountData[];
    officeOptions: Array<{ id: number; name: string }>;
};

const props = defineProps<Props>();
const page = usePage();
const symbol = computed(() => page.props.settings.currencySymbol);

const incomeRows = computed(() =>
    props.rows.filter((row) => row.direction === 'in'),
);
const costRows = computed(() =>
    props.rows.filter((row) => row.direction === 'out'),
);

function money(cents: number): string {
    return formatMoney(cents, symbol.value);
}

function go(params: Record<string, string | number | null | undefined>) {
    router.get(
        '/reports/cash',
        {
            from: props.from,
            to: props.to,
            office: props.filters.office ?? undefined,
            account: props.filters.account ?? undefined,
            ...params,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function monthLabel(month: string): string {
    return new Date(`${month}-01T12:00:00Z`).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    });
}
</script>

<template>
    <Head title="Cash report" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Cash report"
            description="Money that actually moved, by month and by category. Transfers between your own accounts are not counted."
        />

        <div class="flex flex-wrap items-end gap-3">
            <div class="w-40">
                <DateInput
                    :model-value="props.from"
                    placeholder="From"
                    @update:model-value="(v) => go({ from: v })"
                />
            </div>
            <div class="w-40">
                <DateInput
                    :model-value="props.to"
                    placeholder="To"
                    @update:model-value="(v) => go({ to: v })"
                />
            </div>
            <Select
                v-if="props.officeOptions.length > 0"
                :model-value="
                    props.filters.office === null
                        ? 'all'
                        : String(props.filters.office)
                "
                @update:model-value="
                    (v) =>
                        go({
                            office: String(v) === 'all' ? undefined : Number(v),
                            account: undefined,
                        })
                "
            >
                <SelectTrigger class="w-44" aria-label="Office"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All offices</SelectItem>
                    <SelectItem
                        v-for="office in props.officeOptions"
                        :key="office.id"
                        :value="String(office.id)"
                        >{{ office.name }}</SelectItem
                    >
                </SelectContent>
            </Select>
            <Select
                :model-value="
                    props.filters.account === null
                        ? 'all'
                        : String(props.filters.account)
                "
                @update:model-value="
                    (v) =>
                        go({
                            account:
                                String(v) === 'all' ? undefined : Number(v),
                        })
                "
            >
                <SelectTrigger class="w-48" aria-label="Account"
                    ><SelectValue
                /></SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All accounts</SelectItem>
                    <SelectItem
                        v-for="account in props.accounts"
                        :key="account.id"
                        :value="String(account.id)"
                    >
                        {{ account.name }} · {{ account.officeName }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <p v-if="!props.includesPayroll" class="text-sm text-muted-foreground">
            Payroll is not included in this view.
        </p>

        <div class="grid gap-4 sm:grid-cols-3">
            <StatCard label="Money in" :value="money(props.totals.inCents)" />
            <StatCard label="Money out" :value="money(props.totals.outCents)" />
            <StatCard
                label="Cash result"
                :value="money(props.totals.resultCents)"
                hint="Money in minus money out. Not an accounting profit: it ignores unpaid dues and non-cash items."
            />
        </div>

        <Card>
            <CardHeader
                ><CardTitle>By month and category</CardTitle></CardHeader
            >
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="px-4 py-2 font-medium">Category</th>
                                <th
                                    v-for="month in props.months"
                                    :key="month"
                                    class="px-3 py-2 text-right font-medium"
                                >
                                    {{ monthLabel(month) }}
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Total
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="bg-muted/40">
                                <td
                                    :colspan="props.months.length + 2"
                                    class="px-4 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Money in
                                </td>
                            </tr>
                            <tr
                                v-for="row in incomeRows"
                                :key="'in' + row.category"
                                class="border-b"
                            >
                                <td class="px-4 py-2">{{ row.category }}</td>
                                <td
                                    v-for="month in props.months"
                                    :key="month"
                                    class="px-3 py-2 text-right tabular-nums"
                                >
                                    {{
                                        row.months[month]
                                            ? money(row.months[month])
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-medium tabular-nums"
                                >
                                    {{ money(row.totalCents) }}
                                </td>
                            </tr>
                            <tr class="border-b font-medium">
                                <td class="px-4 py-2">Total in</td>
                                <td
                                    v-for="month in props.months"
                                    :key="month"
                                    class="px-3 py-2 text-right tabular-nums"
                                >
                                    {{ money(props.totals.in[month]) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(props.totals.inCents) }}
                                </td>
                            </tr>
                            <tr class="bg-muted/40">
                                <td
                                    :colspan="props.months.length + 2"
                                    class="px-4 py-1 text-xs font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    Money out
                                </td>
                            </tr>
                            <tr
                                v-for="row in costRows"
                                :key="'out' + row.category"
                                class="border-b"
                            >
                                <td class="px-4 py-2">{{ row.category }}</td>
                                <td
                                    v-for="month in props.months"
                                    :key="month"
                                    class="px-3 py-2 text-right tabular-nums"
                                >
                                    {{
                                        row.months[month]
                                            ? money(row.months[month])
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-medium tabular-nums"
                                >
                                    {{ money(row.totalCents) }}
                                </td>
                            </tr>
                            <tr class="border-b font-medium">
                                <td class="px-4 py-2">Total out</td>
                                <td
                                    v-for="month in props.months"
                                    :key="month"
                                    class="px-3 py-2 text-right tabular-nums"
                                >
                                    {{ money(props.totals.out[month]) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(props.totals.outCents) }}
                                </td>
                            </tr>
                            <tr class="font-semibold">
                                <td class="px-4 py-2">Cash result</td>
                                <td
                                    v-for="month in props.months"
                                    :key="month"
                                    class="px-3 py-2 text-right tabular-nums"
                                >
                                    {{ money(props.totals.result[month]) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(props.totals.resultCents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader><CardTitle>By office</CardTitle></CardHeader>
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="px-4 py-2 font-medium">Office</th>
                                <th class="px-3 py-2 text-right font-medium">
                                    In
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Out
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Cash result
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Balance now
                                </th>
                                <th class="px-3 py-2 text-right font-medium">
                                    Inter-office sent
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Inter-office received
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="office in props.offices"
                                :key="office.officeId"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-2 font-medium">
                                    {{ office.officeName }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ money(office.inCents) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ money(office.outCents) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ money(office.resultCents) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ money(office.balanceCents) }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ money(office.sentCents) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(office.receivedCents) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="border-t p-4 text-xs text-muted-foreground">
                    Each office is measured by its own accounts: money in and
                    out are the entries on its accounts. Excel export arrives in
                    a later phase.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
