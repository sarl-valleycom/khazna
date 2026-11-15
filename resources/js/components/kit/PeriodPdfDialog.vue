<script setup lang="ts">
import { ref, watch } from 'vue';
import { FileText } from '@lucide/vue';
import DateInput from '@/components/kit/DateInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { toCalendarDate } from '@/lib/format';

type Props = {
    /** The PDF route, without a query string. */
    href: string;
    title: string;
    description?: string;
    buttonLabel?: string;
};

const props = withDefaults(defineProps<Props>(), {
    description: 'Choose the period the document covers.',
    buttonLabel: 'PDF',
});

const open = ref(false);
const from = ref<string | null>(null);
const to = ref<string | null>(null);

// Open on the current month, the period most people want.
watch(open, (value) => {
    if (!value) {
        return;
    }

    const now = new Date();

    from.value = toCalendarDate(new Date(now.getFullYear(), now.getMonth(), 1));
    to.value = toCalendarDate(
        new Date(now.getFullYear(), now.getMonth() + 1, 0),
    );
});

function submit() {
    if (!from.value || !to.value) {
        return;
    }

    const query = new URLSearchParams({ from: from.value, to: to.value });

    window.open(`${props.href}?${query.toString()}`, '_blank');
    open.value = false;
}
</script>

<template>
    <Button variant="outline" @click="open = true">
        <FileText class="mr-1 size-4" /> {{ buttonLabel }}
    </Button>

    <FormDialog
        v-model:open="open"
        :title="title"
        :description="description"
        submit-label="Open PDF"
        :submit-disabled="!from || !to || to < from"
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="pdf-from">From</Label>
                <DateInput id="pdf-from" v-model="from" />
            </div>
            <div class="space-y-2">
                <Label for="pdf-to">To</Label>
                <DateInput id="pdf-to" v-model="to" />
            </div>
        </div>
    </FormDialog>
</template>
