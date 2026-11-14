<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DateInput from '@/components/kit/DateInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import { toCalendarDate } from '@/lib/format';

type Props = {
    offices: App.Data.OfficeOptionData[];
    types: Array<{ value: string; label: string }>;
    account?: App.Data.AccountRowData | null;
};

const props = withDefaults(defineProps<Props>(), { account: null });
const open = defineModel<boolean>('open', { default: false });
const page = usePage();

const form = useForm({
    office_id: null as number | null,
    name: '',
    type: 'cash',
    opening_balance_cents: 0 as number | null,
    opening_date: toCalendarDate(new Date()) as string | null,
    active: true,
});

const editing = computed(() => props.account !== null);

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();

    if (props.account) {
        form.office_id = props.account.officeId;
        form.name = props.account.name;
        form.type = props.account.type;
        form.opening_balance_cents = props.account.openingBalanceCents;
        form.opening_date = props.account.openingDate;
        form.active = props.account.active;
        return;
    }

    form.office_id = props.offices[0]?.id ?? null;
    form.opening_date = toCalendarDate(new Date());
});

function submit() {
    if (props.account) {
        form.transform(({ office_id: _office, ...rest }) => rest).patch(
            `/accounts/${props.account.id}`,
            {
                preserveScroll: true,
                onSuccess: () => (open.value = false),
            },
        );
        return;
    }

    form.post('/accounts', {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="editing ? 'Edit account' : 'New account'"
        :description="
            editing && props.account?.openingLocked
                ? 'This account has entries, so its opening balance and date are locked.'
                : 'Count the cash or read the balance on one date, and enter it as the opening balance.'
        "
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Office</Label>
                <Select
                    :model-value="
                        form.office_id === null
                            ? undefined
                            : String(form.office_id)
                    "
                    :disabled="editing"
                    @update:model-value="(v) => (form.office_id = Number(v))"
                >
                    <SelectTrigger
                        ><SelectValue placeholder="Choose an office"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="office in props.offices"
                            :key="office.id"
                            :value="String(office.id)"
                        >
                            {{ office.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label>Type</Label>
                <Select v-model="form.type">
                    <SelectTrigger aria-label="Account type"
                        ><SelectValue
                    /></SelectTrigger>
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

        <div class="space-y-2">
            <Label for="account-name">Name</Label>
            <Input
                id="account-name"
                v-model="form.name"
                placeholder="Cash box, CCP, BNA…"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="account-opening">Opening balance</Label>
                <MoneyInput
                    id="account-opening"
                    v-model="form.opening_balance_cents"
                    :symbol="page.props.settings.currencySymbol"
                    :disabled="props.account?.openingLocked"
                />
            </div>
            <div class="space-y-2">
                <Label for="account-opening-date">Opening date</Label>
                <DateInput
                    id="account-opening-date"
                    v-model="form.opening_date"
                    :disabled="props.account?.openingLocked"
                />
            </div>
        </div>

        <div v-if="editing" class="flex items-center gap-2">
            <Checkbox id="account-active" v-model="form.active" />
            <Label for="account-active">Active</Label>
        </div>
    </FormDialog>
</template>
