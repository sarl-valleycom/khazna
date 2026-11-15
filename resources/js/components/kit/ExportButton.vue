<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Download, FileText } from '@lucide/vue';
import { Button } from '@/components/ui/button';

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

// The file holds what the screen shows, so it carries the screen's own filters.
const url = computed(() => {
    const at = page.url.indexOf('?');

    return at === -1 ? props.href : props.href + page.url.slice(at);
});
</script>

<template>
    <Button variant="outline" as-child>
        <a
            :href="url"
            :target="pdf ? '_blank' : undefined"
            :rel="pdf ? 'noopener' : undefined"
            :download="pdf ? undefined : ''"
        >
            <component :is="pdf ? FileText : Download" class="mr-1 size-4" />
            {{ label ?? (pdf ? 'PDF' : 'Export to Excel') }}
        </a>
    </Button>
</template>
