<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Download, FileText } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { downloadFile, openPdf } from '@/lib/files';

type Props = {
    /** The export route, without a query string. */
    href: string;
    label?: string;
    /** A PDF opens in a new tab; an Excel file downloads. */
    pdf?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    label: undefined,
    pdf: false,
});
const page = usePage();
const busy = ref(false);

// The file holds what the screen shows, so it carries the screen's own filters.
const url = computed(() => {
    const at = page.url.indexOf('?');

    return at === -1 ? props.href : props.href + page.url.slice(at);
});

async function run() {
    busy.value = true;

    try {
        await (props.pdf ? openPdf(url.value) : downloadFile(url.value));
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Button variant="outline" :disabled="busy" @click="run">
        <component :is="pdf ? FileText : Download" class="mr-1 size-4" />
        {{ busy ? 'Preparing…' : (label ?? (pdf ? 'PDF' : 'Export to Excel')) }}
    </Button>
</template>
