<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AccountSelect from '@/components/kit/AccountSelect.vue';
import DateInput from '@/components/kit/DateInput.vue';
import FileInput from '@/components/kit/FileInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import { Input } from '@/components/ui/input';
import { formatMoney, toCalendarDate } from '@/lib/format';

type Props = {
    due: App.Data.DueRowData;
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const page = usePage<{
    paymentAccounts?: App.Data.PaymentAccountData[];
    paymentDefaults?: Record<number, number>;
}>();

const methods = [
    { value: 'cash', label: 'Cash' },
    { value: 'ccp', label: 'CCP' },
    { value: 'baridimob', label: 'BaridiMob' },
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'other', label: 'Other' },
];

const accounts = computed(() => page.props.paymentAccounts ?? []);
// For money going out the same dialog reads the other way round.
const isOut = computed(() => props.due.direction === 'out');
const symbol = computed(() => page.props.settings.currencySymbol);
const today = computed(() => toCalendarDate(new Date()));

const form = useForm({
    amount_cents: null as number | null,
    account_id: null as number | null,
    method: 'cash',
    paid_on: toCalendarDate(new Date()) as string | null,
    reference: '',
    notes: '',
    receipt: null as File | null,
    confirm_overpayment: false,
});

function defaultAccount(): number | null {
    const remembered =
        props.due.clientId === null
            ? undefined
            : page.props.paymentDefaults?.[props.due.clientId];

    if (remembered !== undefined) {
        return remembered;
    }

    const sameOffice = accounts.value.filter(
        (account) => account.officeId === props.due.officeId,
    );

    // A client's last account wins; otherwise the office's own account if it has just one,
    // and for a salary or supplier due (no client) the office's first account.
    if (sameOffice.length === 1) {
        return sameOffice[0].id;
    }

    return props.due.clientId === null ? (sameOffice[0]?.id ?? null) : null;
}

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();
    form.amount_cents = props.due.remainingCents;
    form.account_id = defaultAccount();
    form.paid_on = today.value;
});

const needsConfirmation = computed(
    () => form.errors.confirm_overpayment !== undefined,
);

// Changing the amount after a warning starts the confirmation over.
watch(
    () => form.amount_cents,
    () => {
        if (needsConfirmation.value) {
            form.clearErrors('confirm_overpayment');
            form.confirm_overpayment = false;
        }
    },
);

const overpaying = computed(
    () => (form.amount_cents ?? 0) > props.due.remainingCents,
);

function submit() {
    form.post(`/dues/${props.due.id}/payments`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="isOut ? 'Pay out' : 'Record payment'"
        :description="`${props.due.partyName} · ${props.due.kindLabel}${props.due.dueOn ? ` due ${props.due.dueOn}` : ''}. Remaining ${formatMoney(props.due.remainingCents, symbol)}.`"
        :submit-label="isOut ? 'Pay' : 'Record payment'"
        :processing="form.processing"
        :submit-disabled="needsConfirmation && !form.confirm_overpayment"
        :errors="form.errors"
        wide
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="pay-amount">{{
                    isOut ? 'Amount paid' : 'Amount received'
                }}</Label>
                <MoneyInput
                    id="pay-amount"
                    v-model="form.amount_cents"
                    :symbol="symbol"
                />
                <p
                    v-if="overpaying"
                    class="text-xs text-amber-700 dark:text-amber-400"
                >
                    This is more than the remaining amount. You will be asked to
                    confirm it as a credit.
                </p>
            </div>

            <div class="space-y-2">
                <Label for="pay-date">Payment date</Label>
                <DateInput id="pay-date" v-model="form.paid_on" :max="today" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Account</Label>
                <AccountSelect
                    v-model="form.account_id"
                    :accounts="accounts"
                    :allow-all="false"
                    :placeholder="
                        isOut
                            ? 'Which account did it leave?'
                            : 'Where did the money go?'
                    "
                />
                <p
                    v-if="accounts.length === 0"
                    class="text-xs text-destructive"
                >
                    No account is available to you. Ask the owner to create one.
                </p>
            </div>

            <div class="space-y-2">
                <Label>Method</Label>
                <Select v-model="form.method">
                    <SelectTrigger aria-label="Payment method"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="method in methods"
                            :key="method.value"
                            :value="method.value"
                        >
                            {{ method.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="pay-reference">Reference (optional)</Label>
            <Input
                id="pay-reference"
                v-model="form.reference"
                placeholder="Cheque, transfer or receipt number"
            />
        </div>

        <div class="space-y-2">
            <Label>Receipt (optional)</Label>
            <FileInput
                v-model="form.receipt"
                accept="image/jpeg,image/png,image/webp,.pdf"
                hint="JPG, PNG, WebP or PDF"
                @invalid="(message) => form.setError('receipt', message)"
            />
        </div>

        <div class="space-y-2">
            <Label for="pay-notes">Notes (optional)</Label>
            <textarea
                id="pay-notes"
                v-model="form.notes"
                rows="2"
                class="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>

        <div
            v-if="needsConfirmation"
            class="flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950"
        >
            <Checkbox
                id="pay-confirm"
                v-model="form.confirm_overpayment"
                class="mt-0.5"
            />
            <Label for="pay-confirm" class="font-normal">
                Yes, record this overpayment. The extra amount will show as a
                credit on the due and will not be applied to other dues.
            </Label>
        </div>
    </FormDialog>
</template>
