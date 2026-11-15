<script setup lang="ts">
import { ref, watch } from 'vue';
import { FileText } from '@lucide/vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { openPdf } from '@/lib/files';

type Props = {
    /** The PDF route, without a query string. */
    href: string;
    title: string;
    description?: string;
    buttonLabel?: string;
    /** Extra query values to send along, such as the chosen office. */
    extra?: Record<string, string | number | null | undefined>;
};

const props = withDefaults(defineProps<Props>(), {
    description: 'Choose the month the document covers.',
    buttonLabel: 'PDF',
    extra: () => ({}),
});

const open = ref(false);
const month = ref('');

watch(open, (value) => {
    if (!value) {
        return;
    }

    const now = new Date();

    month.value = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
});

function submit() {
    if (!/^\d{4}-\d{2}$/.test(month.value)) {
        return;
    }

    const query = new URLSearchParams({ month: month.value });

    Object.entries(props.extra).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            query.set(key, String(value));
        }
    });

    void openPdf(`${props.href}?${query.toString()}`);
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
        :submit-disabled="!/^\d{4}-\d{2}$/.test(month)"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="pdf-month">Month</Label>
            <Input id="pdf-month" v-model="month" type="month" />
        </div>
    </FormDialog>
</template>
