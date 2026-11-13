<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';

type Props = {
    status: string;
    labels?: Record<string, string>;
};

const props = withDefaults(defineProps<Props>(), {
    labels: () => ({}),
});

const tone = computed(() => {
    switch (props.status) {
        case 'paid':
        case 'active':
        case 'completed':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300';
        case 'pending':
        case 'partial':
        case 'warning':
            return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300';
        case 'overdue':
        case 'failed':
        case 'canceled':
        case 'cancelled':
            return 'border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300';
        case 'draft':
        case 'inactive':
            return 'border-neutral-200 bg-neutral-100 text-neutral-700 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300';
        default:
            return '';
    }
});

const label = computed(
    () =>
        props.labels[props.status] ??
        props.status.charAt(0).toUpperCase() + props.status.slice(1),
);
</script>

<template>
    <Badge variant="outline" class="gap-1.5 font-medium" :class="tone">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ label }}
    </Badge>
</template>
