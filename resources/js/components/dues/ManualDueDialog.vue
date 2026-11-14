<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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

type Option = { value: string; label: string };

type Props = {
    clientId: number;
    symbol: string;
    kinds: Option[];
    directions: Option[];
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    kind: 'saas',
    direction: 'in',
    due_on: toCalendarDate(new Date()),
    amount_cents: null as number | null,
    description: '',
});

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();
    form.due_on = toCalendarDate(new Date());
});

function submit() {
    form.post(`/clients/${props.clientId}/dues`, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        title="New due"
        description="Create a one-off due without a contract."
        submit-label="Create due"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Kind</Label>
                <Select v-model="form.kind">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="kind in props.kinds"
                            :key="kind.value"
                            :value="kind.value"
                        >
                            {{ kind.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="space-y-2">
                <Label>Direction</Label>
                <Select v-model="form.direction">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="direction in props.directions"
                            :key="direction.value"
                            :value="direction.value"
                        >
                            {{ direction.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="manual-due-date">Due date</Label>
                <DateInput id="manual-due-date" v-model="form.due_on" />
            </div>

            <div class="space-y-2">
                <Label for="manual-due-amount">Amount</Label>
                <MoneyInput
                    id="manual-due-amount"
                    v-model="form.amount_cents"
                    :symbol="props.symbol"
                />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="manual-due-description">Description (optional)</Label>
            <textarea
                id="manual-due-description"
                v-model="form.description"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>
</template>
