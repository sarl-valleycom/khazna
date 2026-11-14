<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import FormDialog from '@/components/kit/FormDialog.vue';

type Props = {
    due: App.Data.DueRowData;
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({ reason: '' });

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();
});

function submit() {
    form.patch(`/dues/${props.due.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        title="Cancel due"
        :description="`Cancel ${props.due.kindLabel} due on ${props.due.dueOn}. This cannot be undone from the UI.`"
        submit-label="Cancel due"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="due-cancel-reason">Reason</Label>
            <textarea
                id="due-cancel-reason"
                v-model="form.reason"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>
</template>
