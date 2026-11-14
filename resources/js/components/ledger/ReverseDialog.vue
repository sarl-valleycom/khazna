<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import FormDialog from '@/components/kit/FormDialog.vue';

type Props = {
    /** Where to post the reversal, e.g. /accounts/transfers/3/reverse. */
    action: string;
    title: string;
    description: string;
    submitLabel?: string;
};

const props = withDefaults(defineProps<Props>(), { submitLabel: 'Reverse' });
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ reason: '' });

watch(open, (value) => {
    if (value) {
        form.reset();
        form.clearErrors();
    }
});

function submit() {
    form.post(props.action, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="props.title"
        :description="props.description"
        :submit-label="props.submitLabel"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="reverse-ledger-reason">Reason</Label>
            <textarea
                id="reverse-ledger-reason"
                v-model="form.reason"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>
</template>
