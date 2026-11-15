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

const variant = computed(() => {
    switch (props.status) {
        case 'paid':
        case 'active':
        case 'completed':
        case 'approved':
        case 'closed':
            return 'success';
        case 'pending':
        case 'partial':
        case 'warning':
        case 'paused':
        case 'open':
            return 'warning';
        case 'overdue':
        case 'failed':
        case 'canceled':
        case 'cancelled':
        case 'rejected':
        case 'reversed':
            return 'danger';
        case 'draft':
        case 'inactive':
            return 'neutral';
        default:
            return 'outline';
    }
});

const label = computed(
    () =>
        props.labels[props.status] ??
        props.status.charAt(0).toUpperCase() + props.status.slice(1),
);
</script>

<template>
    <Badge :variant="variant" class="gap-1.5 font-medium">
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        {{ label }}
    </Badge>
</template>
