<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatMoney } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payroll', href: '/payroll/close' }] },
});

type Props = {
    month: string;
    monthLabel: string;
    lines: App.Data.PayrollLineData[];
    readyCount: number;
    totalCents: number;
    totalFormatted: string;
    result: { created: number; existing: number; skipped: number } | null;
};

const props = defineProps<Props>();
const page = usePage();
const month = ref(props.month);
const confirmOpen = ref(false);
const processing = ref(false);

function go() {
    router.get(
        '/payroll/close',
        { month: month.value },
        { preserveState: true, replace: true },
    );
}

function close() {
    processing.value = true;
    router.post(
        '/payroll/close',
        { month: props.month },
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = false;
                confirmOpen.value = false;
            },
        },
    );
}

function money(cents: number): string {
    return formatMoney(cents, page.props.settings.currencySymbol);
}
</script>

<template>
    <Head title="Month-close" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Month-close"
            :description="`Salaries for ${props.monthLabel}. Nothing is written until you confirm.`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/payroll/adjustments?month=${props.month}`">
                        Adjustments
                    </Link>
                </Button>
                <Button
                    :disabled="props.readyCount === 0"
                    @click="confirmOpen = true"
                >
                    Close {{ props.monthLabel }}
                </Button>
            </template>
        </PageHeader>

        <form class="max-w-xs" @submit.prevent="go">
            <Input
                v-model="month"
                type="month"
                aria-label="Month"
                @change="go"
            />
        </form>

        <p
            v-if="props.result"
            class="rounded-md border bg-muted/40 p-3 text-sm"
            role="status"
        >
            Created {{ props.result.created }} salary
            {{ props.result.created === 1 ? 'due' : 'dues' }},
            {{ props.result.existing }} already existed,
            {{ props.result.skipped }} had nothing to pay.
        </p>

        <Card>
            <CardContent v-if="props.lines.length > 0" class="p-0">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-muted-foreground"
                            >
                                <th class="px-4 py-2 font-medium">Employee</th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Salary
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Bonuses
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Deductions
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Advances
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Net
                                </th>
                                <th class="px-4 py-2 font-medium">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="line in props.lines"
                                :key="line.employeeId"
                                class="border-b last:border-0"
                            >
                                <td class="px-4 py-2">
                                    <p class="font-medium">
                                        {{ line.employeeName }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ line.officeName }}
                                    </p>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(line.salaryCents) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(line.bonusCents) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(line.deductionCents) }}
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums">
                                    {{ money(line.advanceCents) }}
                                </td>
                                <td
                                    class="px-4 py-2 text-right font-medium tabular-nums"
                                >
                                    {{ money(line.netCents) }}
                                </td>
                                <td class="px-4 py-2">
                                    <StatusBadge
                                        :status="
                                            line.state === 'ready'
                                                ? 'pending'
                                                : line.state === 'exists'
                                                  ? 'paid'
                                                  : 'inactive'
                                        "
                                        :labels="{
                                            pending: 'Ready',
                                            paid: 'Closed',
                                            inactive: 'Skipped',
                                        }"
                                    />
                                    <p
                                        v-if="line.note"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ line.note }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="border-t p-4 text-xs text-muted-foreground">
                    Net = salary + bonuses − deductions − advances. The salary
                    is the amount in force on the last day of the month. People
                    who joined or left during the month are not prorated: add a
                    deduction.
                </p>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No one to pay"
                    description="Add employees with a salary to prepare a month."
                />
            </CardContent>
        </Card>
    </div>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="`Close ${props.monthLabel}?`"
        :description="`This creates ${props.readyCount} salary ${props.readyCount === 1 ? 'due' : 'dues'} totalling ${props.totalFormatted}. Running it again never duplicates a due.`"
        confirm-label="Close the month"
        :processing="processing"
        @confirm="close"
    />
</template>
