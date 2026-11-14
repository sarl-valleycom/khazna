<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
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
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import { toCalendarDate } from '@/lib/format';

type Props = {
    employees: Array<{ id: number; name: string }>;
    accounts: App.Data.PaymentAccountData[];
    types: Array<{ value: string; label: string }>;
    /** Month as YYYY-MM. */
    month: string;
    /** Preselect these when the dialog is opened from an employee or a salary due. */
    employeeId?: number | null;
    type?: string;
};

const props = withDefaults(defineProps<Props>(), {
    employeeId: null,
    type: 'bonus',
});

const open = defineModel<boolean>('open', { default: false });
const page = usePage();

const methods = [
    { value: 'cash', label: 'Cash' },
    { value: 'ccp', label: 'CCP' },
    { value: 'baridimob', label: 'BaridiMob' },
    { value: 'bank_transfer', label: 'Bank transfer' },
    { value: 'other', label: 'Other' },
];

const form = useForm({
    employee_id: null as number | null,
    type: 'bonus',
    month: props.month,
    amount_cents: null as number | null,
    reason: '',
    paid_on: null as string | null,
    method: 'cash',
    account_id: null as number | null,
    correction_reason: '',
});

const isAdvance = computed(() => form.type === 'advance');
const needsCorrection = computed(
    () => form.errors.correction_reason !== undefined,
);

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();
    form.employee_id = props.employeeId;
    form.type = props.type;
    form.month = props.month;
    form.paid_on = toCalendarDate(new Date());
});

function submit() {
    form.transform((data) =>
        data.type === 'advance'
            ? data
            : {
                  ...data,
                  paid_on: null,
                  method: null,
                  account_id: null,
              },
    ).post('/payroll/adjustments', {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="isAdvance ? 'Pay an advance' : 'Add an adjustment'"
        description="Counts toward the month you choose. An advance also leaves an account today."
        :processing="form.processing"
        :errors="form.errors"
        wide
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Employee</Label>
                <Select
                    :model-value="
                        form.employee_id === null
                            ? undefined
                            : String(form.employee_id)
                    "
                    :disabled="props.employeeId !== null"
                    @update:model-value="(v) => (form.employee_id = Number(v))"
                >
                    <SelectTrigger aria-label="Employee">
                        <SelectValue placeholder="Choose an employee" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="employee in props.employees"
                            :key="employee.id"
                            :value="String(employee.id)"
                        >
                            {{ employee.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label>Type</Label>
                <Select v-model="form.type">
                    <SelectTrigger aria-label="Type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in props.types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="adjustment-amount">Amount</Label>
                <MoneyInput
                    id="adjustment-amount"
                    v-model="form.amount_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
            <div class="space-y-2">
                <Label for="adjustment-month">Month</Label>
                <Input
                    id="adjustment-month"
                    v-model="form.month"
                    type="month"
                />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="adjustment-reason">Reason</Label>
            <Input id="adjustment-reason" v-model="form.reason" />
        </div>

        <template v-if="isAdvance">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="advance-date">Paid on</Label>
                    <DateInput
                        id="advance-date"
                        v-model="form.paid_on"
                        :max="toCalendarDate(new Date())"
                    />
                </div>
                <div class="space-y-2">
                    <Label>Method</Label>
                    <Select v-model="form.method">
                        <SelectTrigger aria-label="Payment method">
                            <SelectValue />
                        </SelectTrigger>
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
                <Label>Paid from</Label>
                <AccountSelect
                    v-model="form.account_id"
                    :accounts="props.accounts"
                    :allow-all="false"
                    placeholder="Which account did it leave?"
                />
            </div>
        </template>

        <div
            v-if="needsCorrection"
            class="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950"
        >
            <Label for="correction-reason">
                This month's salary is already paid. Why is it being changed?
            </Label>
            <Input id="correction-reason" v-model="form.correction_reason" />
        </div>
    </FormDialog>
</template>
