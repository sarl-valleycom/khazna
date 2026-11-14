<script setup lang="ts">
import { computed } from 'vue';
import { ArrowDownRight, ArrowUpRight, Minus } from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type Props = {
    label: string;
    value: string | number;
    hint?: string;
    delta?: number | null;
    deltaSuffix?: string;
    loading?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    delta: null,
    deltaSuffix: '%',
    loading: false,
});

const deltaTone = computed(() => {
    if (props.delta === null || props.delta === 0) {
        return 'text-muted-foreground';
    }

    return props.delta > 0
        ? 'text-emerald-700 dark:text-emerald-400'
        : 'text-red-700 dark:text-red-400';
});

const deltaIcon = computed(() => {
    if (props.delta === null || props.delta === 0) {
        return Minus;
    }

    return props.delta > 0 ? ArrowUpRight : ArrowDownRight;
});
</script>

<template>
    <Card>
        <CardContent class="p-5">
            <p class="text-sm font-medium text-muted-foreground">{{ label }}</p>
            <Skeleton v-if="loading" class="mt-3 h-8 w-32" />
            <p
                v-else
                class="mt-3 text-2xl font-semibold tracking-tight tabular-nums"
            >
                {{ value }}
            </p>
            <div class="mt-2 flex items-center gap-2 text-xs">
                <span
                    v-if="delta !== null"
                    class="inline-flex items-center gap-0.5 font-medium"
                    :class="deltaTone"
                >
                    <component :is="deltaIcon" class="size-3.5" />
                    {{ Math.abs(delta) }}{{ deltaSuffix }}
                </span>
                <span v-if="hint" class="text-muted-foreground">{{
                    hint
                }}</span>
            </div>
        </CardContent>
    </Card>
</template>
