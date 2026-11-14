<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import FormDialog from '@/components/kit/FormDialog.vue';

type Props = {
    payment: App.Data.PaymentRowData;
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({ reason: '' });

watch(open, (value) => {
    if (value) {
        form.reset();
        form.clearErrors();
    }
});

function submit() {
    form.post(`/payments/${props.payment.id}/reverse`, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        title="Reverse payment"
        :description="`Reverse ${props.payment.amountFormatted} paid on ${props.payment.paidOn}. The original entry stays in the account statement and an opposite entry is added.`"
        submit-label="Reverse payment"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="reverse-reason">Reason</Label>
            <textarea
                id="reverse-reason"
                v-model="form.reason"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>
</template>
