<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import AccountSelect from '@/components/kit/AccountSelect.vue';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import DateInput from '@/components/kit/DateInput.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatMoney, toCalendarDate } from '@/lib/format';

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
    accounts: App.Data.PaymentAccountData[];
    methods: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const page = usePage();
const month = ref(props.month);

// A refused close (no account chosen, a payment date before the account opened, ...)
// comes back as validation errors; without this the page would show nothing.
const errorMessages = computed(() =>
    Object.values((page.props.errors ?? {}) as Record<string, string>),
);
const confirmOpen = ref(false);
const processing = ref(false);

// The account that pays each ready employee, when paying at close time.
const payAccounts = reactive<Record<number, number | null>>({});
const sameAccount = ref<number | null>(null);

// The owner may change what each ready employee's due will be. It starts at the worked-out net.
const amounts = reactive<Record<number, number | null>>({});

watch(
    () => props.lines,
    (lines) => {
        for (const key of Object.keys(amounts)) {
            delete amounts[Number(key)];
        }

        for (const key of Object.keys(payAccounts)) {
            delete payAccounts[Number(key)];
        }

        for (const line of lines) {
            if (line.state === 'ready') {
                amounts[line.employeeId] = line.netCents;
                payAccounts[line.employeeId] = sameAccount.value;
            }
        }
    },
    { immediate: true },
);

const amountsValid = computed(() =>
    Object.values(amounts).every((value) => value !== null && value > 0),
);

const closingTotalCents = computed(() =>
    Object.values(amounts).reduce<number>(
        (sum, value) => sum + (value ?? 0),
        0,
    ),
);

function edited(line: App.Data.PayrollLineData): boolean {
    return amounts[line.employeeId] !== line.netCents;
}

function go() {
    router.get(
        '/payroll/close',
        { month: month.value },
        { preserveState: true, replace: true },
    );
}

// Optional: pay every new salary due now, each from its own account, on one date.
const payNow = ref(false);
const pay = reactive({
    method: 'cash',
    paid_on: toCalendarDate(new Date()) as string | null,
});

function applySameAccount(accountId: number | null) {
    sameAccount.value = accountId;

    for (const key of Object.keys(payAccounts)) {
        payAccounts[Number(key)] = accountId;
    }
}

const payValid = computed(
    () =>
        !payNow.value ||
        (!!pay.paid_on &&
            Object.values(payAccounts).every((value) => value !== null)),
);

function close() {
    processing.value = true;
    router.post(
        '/payroll/close',
        {
            month: props.month,
            amounts: { ...amounts },
            pay: payNow.value ? { ...pay, accounts: { ...payAccounts } } : null,
        },
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
                    :disabled="
                        props.readyCount === 0 || !amountsValid || !payValid
                    "
                    @click="confirmOpen = true"
                >
                    Close {{ props.monthLabel }}
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="grid gap-4 p-4 text-sm sm:grid-cols-3">
                <div class="space-y-1">
                    <p class="font-medium">1. Adjustments</p>
                    <p class="text-muted-foreground">
                        Add this month's advances, bonuses and deductions. An
                        advance is money that already left an account, so it is
                        taken off the salary.
                    </p>
                    <Link
                        :href="`/payroll/adjustments?month=${props.month}`"
                        class="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Open adjustments
                    </Link>
                </div>
                <div class="space-y-1">
                    <p class="font-medium">2. Close the month (here)</p>
                    <p class="text-muted-foreground">
                        Creates one salary due per employee. No money moves
                        unless you tick “Also pay these salaries now”. Type a
                        different amount in the “Salary due” column to change
                        what a due will be.
                    </p>
                </div>
                <div class="space-y-1">
                    <p class="font-medium">3. Pay the salary</p>
                    <p class="text-muted-foreground">
                        Money leaves an account only when you record a payment
                        on the salary due: choose the account and the date.
                    </p>
                    <Link
                        href="/?kind=salary"
                        class="font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Open salary dues
                    </Link>
                </div>
            </CardContent>
        </Card>

        <form class="max-w-xs" @submit.prevent="go">
            <Input
                v-model="month"
                type="month"
                aria-label="Month"
                @change="go"
            />
        </form>

        <div
            v-if="errorMessages.length > 0"
            class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
            role="alert"
        >
            <p class="font-medium">The month was not closed.</p>
            <ul class="mt-1 list-disc pl-5">
                <li v-for="message in errorMessages" :key="message">
                    {{ message }}
                </li>
            </ul>
        </div>

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
                                    Salary due
                                </th>
                                <th v-if="payNow" class="px-4 py-2 font-medium">
                                    Paid from
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
                                    <MoneyInput
                                        v-if="line.state === 'ready'"
                                        v-model="amounts[line.employeeId]"
                                        :symbol="
                                            page.props.settings.currencySymbol
                                        "
                                        class="ml-auto w-40 text-right"
                                        :aria-label="`Amount for ${line.employeeName}`"
                                    />
                                    <template v-else>{{
                                        money(line.netCents)
                                    }}</template>
                                    <p
                                        v-if="
                                            line.state === 'ready' &&
                                            edited(line)
                                        "
                                        class="mt-1 text-xs text-warning"
                                    >
                                        Edited (worked out:
                                        {{ money(line.netCents) }})
                                    </p>
                                </td>
                                <td v-if="payNow" class="min-w-52 px-4 py-2">
                                    <AccountSelect
                                        v-if="line.state === 'ready'"
                                        v-model="payAccounts[line.employeeId]"
                                        :accounts="props.accounts"
                                        :allow-all="false"
                                        :label="`Account paying ${line.employeeName}`"
                                        placeholder="Choose an account"
                                    />
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
                    Salary due = salary + bonuses − deductions − advances, shown
                    as the amount the due will be. You can type a different
                    amount for any ready employee before closing. The salary is
                    the amount in force on the last day of the month. People who
                    joined or left during the month are not prorated: add a
                    deduction.
                </p>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No one to pay"
                    description="An employee appears here when they were hired on or before the last day of the month and have a salary starting on or before it. For an old month, set an earlier hire date (Edit the employee) and add a salary that starts before the month ends."
                />
            </CardContent>
        </Card>
        <Card v-if="props.readyCount > 0">
            <CardContent class="space-y-4 p-4">
                <div class="flex items-center gap-2">
                    <Checkbox id="pay-now" v-model="payNow" />
                    <Label for="pay-now" class="font-medium">
                        Also pay these salaries now
                    </Label>
                </div>
                <p class="text-sm text-muted-foreground">
                    Off: closing only creates the salary dues, and no money
                    moves. On: each salary is also paid in full, from the
                    account you choose for that employee, on the date you
                    choose.
                </p>
                <div v-if="payNow" class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label>Same account for everyone (optional)</Label>
                        <AccountSelect
                            :model-value="sameAccount"
                            :accounts="props.accounts"
                            :allow-all="false"
                            placeholder="Fill every row with one account"
                            @update:model-value="applySameAccount"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label>Method</Label>
                        <Select v-model="pay.method">
                            <SelectTrigger aria-label="Payment method">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="method in props.methods"
                                    :key="method.value"
                                    :value="method.value"
                                >
                                    {{ method.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="space-y-2">
                        <Label for="pay-date">Paid on</Label>
                        <DateInput
                            id="pay-date"
                            v-model="pay.paid_on"
                            :max="toCalendarDate(new Date())"
                        />
                    </div>
                </div>
                <p v-if="payNow" class="text-sm text-muted-foreground">
                    Choose the account that pays each employee in the “Paid
                    from” column of the table above.
                </p>
            </CardContent>
        </Card>
    </div>

    <ConfirmDialog
        v-model:open="confirmOpen"
        :title="`Close ${props.monthLabel}?`"
        :description="`This creates ${props.readyCount} salary ${props.readyCount === 1 ? 'due' : 'dues'} totalling ${money(closingTotalCents)}. ${payNow ? `Each is also paid from its chosen account on ${pay.paid_on ?? ''}, so ${money(closingTotalCents)} leaves your accounts.` : 'No money moves yet.'} Running it again never duplicates a due.`"
        confirm-label="Close the month"
        :processing="processing"
        @confirm="close"
    />
</template>
