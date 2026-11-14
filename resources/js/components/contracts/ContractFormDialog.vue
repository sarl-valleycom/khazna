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
import DateInput from '@/components/kit/DateInput.vue';
import FileInput from '@/components/kit/FileInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import { toCalendarDate } from '@/lib/format';

type Props = {
    clients: App.Data.ContractClientOptionData[];
    offices: App.Data.OfficeOptionData[];
    cycles: Array<{ value: string; label: string }>;
    canChooseOffice: boolean;
    types?: Array<{ value: string; label: string }>;
    suppliers?: Array<{ id: number; name: string }>;
    categories?: Array<{ id: number; name: string }>;
    contract?: App.Data.ContractData | null;
};

const props = withDefaults(defineProps<Props>(), {
    contract: null,
    types: () => [{ value: 'saas_renewal', label: 'SaaS renewal' }],
    suppliers: () => [],
    categories: () => [],
});

const open = defineModel<boolean>('open', { default: false });
const page = usePage();

const form = useForm({
    type: 'saas_renewal',
    client_id: null as number | null,
    supplier_id: null as number | null,
    category_id: null as number | null,
    amount_cents: null as number | null,
    cycle: 'monthly',
    starts_on: toCalendarDate(new Date()),
    ends_on: null as string | null,
    auto_renew: true,
    notes: '',
    office_id: null as number | null,
    attachment: null as File | null,
});

const editing = computed(() => props.contract !== null);
const isCost = computed(() => form.type === 'recurring_cost');
const title = computed(() =>
    editing.value ? 'Edit contract' : 'New contract',
);

const forcedOfficeName = computed(() => {
    const officeId = page.props.auth.user?.officeId ?? null;

    return (
        props.offices.find((office) => office.id === officeId)?.name ??
        'Your office'
    );
});

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();

    if (props.contract) {
        form.type = props.contract.type;
        form.client_id = props.contract.clientId;
        form.supplier_id = props.contract.supplierId;
        form.category_id = props.contract.categoryId;
        form.amount_cents = props.contract.amountCents;
        form.cycle = props.contract.cycle;
        form.starts_on = props.contract.startsOn;
        form.ends_on = props.contract.endsOn;
        form.auto_renew = props.contract.autoRenew;
        form.notes = props.contract.notes ?? '';
        form.office_id = props.contract.officeId;
        return;
    }

    form.starts_on = toCalendarDate(new Date());
    form.office_id = page.props.auth.user?.officeId ?? null;
});

function chooseClient(value: unknown) {
    form.client_id = Number(value);

    if (!props.canChooseOffice) {
        return;
    }

    const client = props.clients.find((item) => item.id === form.client_id);

    if (client?.officeId) {
        form.office_id = client.officeId;
    }
}

function submit() {
    if (props.contract) {
        form.patch(`/contracts/${props.contract.id}`, {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        });
        return;
    }

    form.post('/contracts', {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="title"
        :description="
            editing
                ? 'Changing the amount only affects dues generated afterwards.'
                : isCost
                  ? 'A cost the company pays to a supplier on a cycle.'
                  : 'Create a SaaS renewal for a client.'
        "
        :processing="form.processing"
        :errors="form.errors"
        wide
        @submit="submit"
    >
        <div v-if="!editing && props.types.length > 1" class="space-y-2">
            <Label>Type</Label>
            <Select v-model="form.type">
                <SelectTrigger aria-label="Contract type">
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

        <div class="grid gap-4 sm:grid-cols-2">
            <div v-if="!isCost" class="space-y-2">
                <Label>Client</Label>
                <Select
                    :model-value="
                        form.client_id === null
                            ? undefined
                            : String(form.client_id)
                    "
                    :disabled="editing"
                    @update:model-value="chooseClient"
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Choose a client" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="client in props.clients"
                            :key="client.id"
                            :value="String(client.id)"
                        >
                            {{ client.name }}
                            <span v-if="client.officeName">
                                · {{ client.officeName }}
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="editing" class="text-xs text-muted-foreground">
                    The client of an existing contract cannot be changed.
                </p>
            </div>

            <div v-if="isCost" class="space-y-2">
                <Label>Supplier</Label>
                <Select
                    :model-value="
                        form.supplier_id === null
                            ? undefined
                            : String(form.supplier_id)
                    "
                    :disabled="editing"
                    @update:model-value="(v) => (form.supplier_id = Number(v))"
                >
                    <SelectTrigger aria-label="Supplier">
                        <SelectValue placeholder="Choose a supplier" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="supplier in props.suppliers"
                            :key="supplier.id"
                            :value="String(supplier.id)"
                        >
                            {{ supplier.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-2">
                <Label for="contract-amount">Amount</Label>
                <MoneyInput
                    id="contract-amount"
                    v-model="form.amount_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
        </div>

        <div v-if="isCost" class="space-y-2">
            <Label>Category</Label>
            <Select
                :model-value="
                    form.category_id === null
                        ? undefined
                        : String(form.category_id)
                "
                @update:model-value="(v) => (form.category_id = Number(v))"
            >
                <SelectTrigger aria-label="Category">
                    <SelectValue placeholder="Choose a category" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="category in props.categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <p class="text-xs text-muted-foreground">
                Every due and payment of this cost is filed under it.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Cycle</Label>
                <Select v-model="form.cycle">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="cycle in props.cycles"
                            :key="cycle.value"
                            :value="cycle.value"
                        >
                            {{ cycle.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-2">
                <Label for="contract-starts-on">Starts on</Label>
                <DateInput id="contract-starts-on" v-model="form.starts_on" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="contract-ends-on">Ends on (optional)</Label>
                <DateInput
                    id="contract-ends-on"
                    v-model="form.ends_on"
                    placeholder="No end date"
                    :min="form.starts_on"
                />
            </div>

            <div class="space-y-2">
                <Label>Office</Label>
                <Select
                    v-if="props.canChooseOffice"
                    :model-value="
                        form.office_id === null
                            ? undefined
                            : String(form.office_id)
                    "
                    @update:model-value="
                        (value) => (form.office_id = Number(value))
                    "
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Choose an office" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="office in props.offices"
                            :key="office.id"
                            :value="String(office.id)"
                        >
                            {{ office.name }} · {{ office.city }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-else
                    class="flex h-9 items-center rounded-md border bg-muted/40 px-3 text-sm"
                >
                    {{ forcedOfficeName }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <Checkbox id="contract-auto-renew" v-model="form.auto_renew" />
            <div>
                <Label for="contract-auto-renew">Auto-renew</Label>
                <p class="text-xs text-muted-foreground">
                    Off means it bills until the end date, or for one cycle if
                    there is none.
                </p>
            </div>
        </div>

        <div class="space-y-2">
            <Label for="contract-notes">Notes</Label>
            <textarea
                id="contract-notes"
                v-model="form.notes"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>

        <div class="space-y-2">
            <Label>Attachment (optional)</Label>
            <FileInput
                v-model="form.attachment"
                accept=".pdf,.jpg,.jpeg,.png,.webp"
                :max-size-mb="10"
            />
            <p
                v-if="props.contract?.attachmentName"
                class="text-xs text-muted-foreground"
            >
                Current: {{ props.contract.attachmentName }}
            </p>
        </div>

        <div
            class="rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
        >
            Changing the amount only affects dues generated afterwards. Existing
            dues keep their amount unless edited explicitly.
        </div>
    </FormDialog>
</template>
