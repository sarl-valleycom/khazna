<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ArrowRight, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ReverseDialog from '@/components/ledger/ReverseDialog.vue';
import AccountSelect from '@/components/kit/AccountSelect.vue';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { formatDate, formatMoney, toCalendarDate } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accounts', href: '/accounts' },
            { title: 'Transfers', href: '/accounts/transfers' },
        ],
    },
});

type Transfer = {
    id: number;
    date: string;
    fromName: string;
    fromOffice: string;
    toName: string;
    toOffice: string;
    amountCents: number;
    feeCents: number;
    note: string | null;
    interOffice: boolean;
    reversed: boolean;
    reversalReason: string | null;
    createdBy: string | null;
    canReverse: boolean;
};

const props = defineProps<{
    transfers: Transfer[];
    accounts: App.Data.PaymentAccountData[];
}>();

const page = usePage();
const open = ref(false);
const reversing = ref<Transfer | null>(null);
const reverseOpen = ref(false);

const form = useForm({
    from_account_id: null as number | null,
    to_account_id: null as number | null,
    amount_cents: null as number | null,
    fee_cents: null as number | null,
    transfer_date: toCalendarDate(new Date()) as string | null,
    note: '',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    form.transfer_date = toCalendarDate(new Date());
    open.value = true;
}

function submit() {
    form.post('/accounts/transfers', {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

function ask(transfer: Transfer) {
    reversing.value = transfer;
    reverseOpen.value = true;
}

function money(cents: number): string {
    return formatMoney(cents, page.props.settings.currencySymbol);
}
</script>

<template>
    <Head title="Transfers" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Transfers"
            description="Money moved between your own accounts. A transfer is not income or expense; only its fee is."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/accounts/entries">Manual entries</Link>
                </Button>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New transfer
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent v-if="props.transfers.length > 0" class="p-0">
                <div class="divide-y">
                    <div
                        v-for="transfer in props.transfers"
                        :key="transfer.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                        :class="transfer.reversed && 'opacity-60'"
                    >
                        <div class="min-w-0">
                            <p
                                class="flex flex-wrap items-center gap-2 font-medium"
                            >
                                {{ transfer.fromName }}
                                <ArrowRight
                                    class="size-4 text-muted-foreground"
                                />
                                {{ transfer.toName }}
                                <span
                                    v-if="transfer.interOffice"
                                    class="rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-xs font-normal text-sky-800 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-300"
                                >
                                    Inter-office: {{ transfer.fromOffice }} →
                                    {{ transfer.toOffice }}
                                </span>
                                <span
                                    v-if="transfer.reversed"
                                    class="text-xs font-normal"
                                    >(reversed)</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(transfer.date) }}
                                <template v-if="transfer.feeCents > 0"
                                    >· fee
                                    {{ money(transfer.feeCents) }}</template
                                >
                                <template v-if="transfer.note"
                                    >· {{ transfer.note }}</template
                                >
                                <template v-if="transfer.createdBy"
                                    >· by {{ transfer.createdBy }}</template
                                >
                                <template v-if="transfer.reversalReason"
                                    >· reversed:
                                    {{ transfer.reversalReason }}</template
                                >
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-medium tabular-nums">{{
                                money(transfer.amountCents)
                            }}</span>
                            <Button
                                v-if="transfer.canReverse"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="ask(transfer)"
                            >
                                Reverse
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No transfers yet"
                    description="Move money between cash, CCP and bank accounts here, so the balances stay true."
                />
            </CardContent>
        </Card>
    </div>

    <FormDialog
        v-model:open="open"
        title="New transfer"
        description="The fee, if any, is taken from the source account."
        :processing="form.processing"
        :errors="form.errors"
        wide
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>From</Label>
                <AccountSelect
                    v-model="form.from_account_id"
                    :accounts="props.accounts"
                    :allow-all="false"
                    label="From account"
                    placeholder="Source account"
                />
            </div>
            <div class="space-y-2">
                <Label>To</Label>
                <AccountSelect
                    v-model="form.to_account_id"
                    :accounts="props.accounts"
                    :allow-all="false"
                    label="To account"
                    placeholder="Destination account"
                />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
                <Label for="transfer-amount">Amount</Label>
                <MoneyInput
                    id="transfer-amount"
                    v-model="form.amount_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
            <div class="space-y-2">
                <Label for="transfer-fee">Fee (optional)</Label>
                <MoneyInput
                    id="transfer-fee"
                    v-model="form.fee_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
            <div class="space-y-2">
                <Label for="transfer-date">Date</Label>
                <DateInput
                    id="transfer-date"
                    v-model="form.transfer_date"
                    :max="toCalendarDate(new Date())"
                />
            </div>
        </div>
        <div class="space-y-2">
            <Label for="transfer-note">Note (optional)</Label>
            <Input id="transfer-note" v-model="form.note" />
        </div>
    </FormDialog>

    <ReverseDialog
        v-if="reversing"
        v-model:open="reverseOpen"
        :action="`/accounts/transfers/${reversing.id}/reverse`"
        title="Reverse transfer"
        description="Both accounts get opposite entries, fee included. The original entries stay in the statements."
    />
</template>
