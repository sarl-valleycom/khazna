<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Label } from '@/components/ui/label';
import DateInput from '@/components/kit/DateInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import { toCalendarDate } from '@/lib/format';

type Props = {
    due: App.Data.DueRowData;
};

const props = defineProps<Props>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    new_due_on: null as string | null,
    reason: '',
});

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();
    form.new_due_on = toCalendarDate(new Date());
});

function submit() {
    form.patch(`/dues/${props.due.id}/postpone`, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        title="Postpone due"
        :description="`Move ${props.due.kindLabel} from ${props.due.dueOn}.`"
        submit-label="Postpone"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="due-postpone-date">New date</Label>
            <DateInput
                id="due-postpone-date"
                v-model="form.new_due_on"
                :min="toCalendarDate(new Date())"
            />
        </div>

        <div class="space-y-2">
            <Label for="due-postpone-reason">Reason</Label>
            <textarea
                id="due-postpone-reason"
                v-model="form.reason"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>
</template>
