<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Download } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Props = {
    /** The export route, without a query string. */
    href: string;
    label?: string;
};

const props = withDefaults(defineProps<Props>(), { label: 'Export to Excel' });
const page = usePage();

// The file holds what the screen shows, so it carries the screen's own filters.
const url = computed(() => {
    const at = page.url.indexOf('?');

    return at === -1 ? props.href : props.href + page.url.slice(at);
});
</script>

<template>
    <Button variant="outline" as-child>
        <a :href="url" download>
            <Download class="mr-1 size-4" /> {{ label }}
        </a>
    </Button>
</template>
