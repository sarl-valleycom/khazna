<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ReverseDialog from '@/components/ledger/ReverseDialog.vue';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { formatDate, formatMoney, toCalendarDate } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accounts', href: '/accounts' },
            { title: 'Reconcile', href: '/accounts/reconcile' },
        ],
    },
});

type AccountChoice = App.Data.PaymentAccountData & {
    lastReconciled: string | null;
    due: boolean;
    everyDays: number;
};

type Row = {
    id: number;
    asOf: string;
    countedCents: number;
    systemCents: number;
    differenceCents: number;
    note: string | null;
    recorded: boolean;
    open: boolean;
    createdBy: string | null;
    canAdjust: boolean;
};

const props = defineProps<{
    accounts: AccountChoice[];
    selectedId: number | null;
    asOf: string;
    systemBalanceCents: number | null;
    openingDate: string | null;
    history: Row[];
}>();

const page = usePage();
const symbol = computed(() => page.props.settings.currencySymbol);
const asOf = ref<string | null>(props.asOf);
const adjusting = ref<Row | null>(null);
const adjustOpen = ref(false);

const form = useForm({
    account_id: props.selectedId,
    as_of: props.asOf,
    counted_balance_cents: null as number | null,
    note: '',
    record_difference: false,
    reason: '',
});

const difference = computed(() =>
    props.systemBalanceCents === null || form.counted_balance_cents === null
        ? null
        : form.counted_balance_cents - props.systemBalanceCents,
);

const dueCount = computed(
    () => props.accounts.filter((account) => account.due).length,
);

function money(cents: number): string {
    return formatMoney(cents, symbol.value);
}

function load(accountId: number | null, date: string | null) {
    router.get(
        '/accounts/reconcile',
        { account: accountId ?? undefined, as_of: date ?? undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function choose(account: AccountChoice) {
    form.account_id = account.id;
    form.counted_balance_cents = null;
    load(account.id, asOf.value);
}

function changeDate(value: string | null) {
    asOf.value = value;
    form.as_of = value ?? toCalendarDate(new Date());
    load(form.account_id, value);
}

function submit() {
    form.post('/accounts/reconcile', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset(
                'counted_balance_cents',
                'note',
                'record_difference',
                'reason',
            );
        },
    });
}

function ask(row: Row) {
    adjusting.value = row;
    adjustOpen.value = true;
}
</script>

<template>
    <Head title="Reconcile" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Reconcile"
            description="Count the cash or read the statement, and Khazna shows what it expects for that day."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/accounts">Accounts</Link>
                </Button>
            </template>
        </PageHeader>

        <p
            v-if="dueCount > 0"
            class="rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950"
            role="status"
        >
            {{ dueCount }}
            {{ dueCount === 1 ? 'account is' : 'accounts are' }} due for a
            count: cash weekly, everything else every 30 days.
        </p>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card>
                <CardHeader><CardTitle>Account</CardTitle></CardHeader>
                <CardContent class="p-0">
                    <div class="divide-y">
                        <button
                            v-for="account in props.accounts"
                            :key="account.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-2 p-4 text-left hover:bg-muted/50"
                            :class="
                                account.id === props.selectedId && 'bg-muted'
                            "
                            :aria-pressed="account.id === props.selectedId"
                            @click="choose(account)"
                        >
                            <span>
                                <span class="block font-medium">{{
                                    account.name
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{ account.officeName }} ·
                                    {{
                                        account.lastReconciled
                                            ? `counted ${formatDate(account.lastReconciled)}`
                                            : 'never counted'
                                    }}
                                </span>
                            </span>
                            <span
                                v-if="account.due"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300"
                            >
                                due
                            </span>
                        </button>
                    </div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader><CardTitle>New count</CardTitle></CardHeader>
                <CardContent>
                    <form
                        v-if="props.selectedId"
                        class="space-y-4"
                        @submit.prevent="submit"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label for="rec-date"
                                    >Balance at the end of</Label
                                >
                                <DateInput
                                    id="rec-date"
                                    :model-value="asOf"
                                    :min="props.openingDate ?? undefined"
                                    :max="toCalendarDate(new Date())"
                                    @update:model-value="changeDate"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Khazna expects</Label>
                                <p
                                    class="flex h-9 items-center text-lg font-semibold tabular-nums"
                                >
                                    {{
                                        props.systemBalanceCents === null
                                            ? '—'
                                            : money(props.systemBalanceCents)
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="rec-counted"
                                >Counted or read from the statement</Label
                            >
                            <MoneyInput
                                id="rec-counted"
                                v-model="form.counted_balance_cents"
                                :symbol="symbol"
                            />
                        </div>

                        <div
                            v-if="difference !== null"
                            class="rounded-md border p-3 text-sm"
                            :class="
                                difference === 0
                                    ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950'
                                    : 'border-amber-300 bg-amber-50 dark:border-amber-800 dark:bg-amber-950'
                            "
                            role="status"
                        >
                            <template v-if="difference === 0"
                                >The balance matches.</template
                            >
                            <template v-else>
                                Difference:
                                <strong
                                    >{{ difference > 0 ? '+' : '−'
                                    }}{{ money(Math.abs(difference)) }}</strong
                                >
                                ({{ difference > 0 ? 'more' : 'less' }} than
                                Khazna expects).
                            </template>
                        </div>

                        <fieldset
                            v-if="difference !== null && difference !== 0"
                            class="space-y-3"
                        >
                            <legend class="text-sm font-medium">
                                What to do with the difference
                            </legend>
                            <label class="flex items-start gap-2 text-sm">
                                <input
                                    v-model="form.record_difference"
                                    type="radio"
                                    :value="true"
                                    class="mt-1"
                                />
                                <span
                                    >Record it as an adjustment, filed under
                                    "Reconciliation difference".</span
                                >
                            </label>
                            <div
                                v-if="form.record_difference"
                                class="ml-6 space-y-2"
                            >
                                <Label for="rec-reason">Reason</Label>
                                <Input id="rec-reason" v-model="form.reason" />
                            </div>
                            <label class="flex items-start gap-2 text-sm">
                                <input
                                    v-model="form.record_difference"
                                    type="radio"
                                    :value="false"
                                    class="mt-1"
                                />
                                <span
                                    >Leave it open, to investigate. Nothing is
                                    changed.</span
                                >
                            </label>
                        </fieldset>

                        <div class="space-y-2">
                            <Label for="rec-note">Note (optional)</Label>
                            <Input id="rec-note" v-model="form.note" />
                        </div>

                        <p
                            v-for="(message, field) in form.errors"
                            :key="field"
                            class="text-sm text-destructive"
                            role="alert"
                        >
                            {{ message }}
                        </p>

                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                form.counted_balance_cents === null
                            "
                        >
                            Save the count
                        </Button>
                    </form>
                    <EmptyState
                        v-else
                        title="No account to reconcile"
                        description="Create an account first."
                    />
                </CardContent>
            </Card>
        </div>

        <Card v-if="props.selectedId">
            <CardHeader><CardTitle>History</CardTitle></CardHeader>
            <CardContent class="p-0">
                <div v-if="props.history.length > 0" class="divide-y">
                    <div
                        v-for="row in props.history"
                        :key="row.id"
                        class="flex flex-col gap-1 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ formatDate(row.asOf) }}: counted
                                {{ money(row.countedCents) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Khazna expected {{ money(row.systemCents) }}
                                <template v-if="row.differenceCents === 0"
                                    >· matched</template
                                >
                                <template v-else>
                                    · difference
                                    {{ row.differenceCents > 0 ? '+' : '−'
                                    }}{{ money(Math.abs(row.differenceCents)) }}
                                    <template v-if="row.recorded"
                                        >· recorded</template
                                    >
                                    <template v-else>· open</template>
                                </template>
                                <template v-if="row.createdBy"
                                    >· by {{ row.createdBy }}</template
                                >
                                <template v-if="row.note"
                                    >· {{ row.note }}</template
                                >
                            </p>
                        </div>
                        <Button
                            v-if="row.open && row.canAdjust"
                            variant="outline"
                            size="sm"
                            @click="ask(row)"
                        >
                            Record the difference
                        </Button>
                    </div>
                </div>
                <EmptyState
                    v-else
                    title="No counts yet"
                    description="Every account should be counted against reality regularly."
                />
            </CardContent>
        </Card>
    </div>

    <ReverseDialog
        v-if="adjusting"
        v-model:open="adjustOpen"
        :action="`/accounts/reconcile/${adjusting.id}/adjust`"
        title="Record the difference"
        description="An adjustment entry is added to the account, dated on the day of the count, with the reason you give."
        submit-label="Record"
    />
</template>
