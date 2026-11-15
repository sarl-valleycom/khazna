<script setup lang="ts">
import { computed, type Component } from 'vue';
import { ArrowDownRight, ArrowUpRight, Minus } from '@lucide/vue';
import { Card, CardContent } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

type Tone = 'neutral' | 'success' | 'warning' | 'danger' | 'info';

type Props = {
    label: string;
    value: string | number;
    hint?: string;
    delta?: number | null;
    deltaSuffix?: string;
    loading?: boolean;
    icon?: Component;
    tone?: Tone;
};

const props = withDefaults(defineProps<Props>(), {
    delta: null,
    deltaSuffix: '%',
    loading: false,
    icon: undefined,
    tone: 'neutral',
});

const chip: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
};

const accent: Record<Tone, string> = {
    neutral: 'bg-border',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
};

const deltaTone = computed(() => {
    if (props.delta === null || props.delta === 0) {
        return 'text-muted-foreground';
    }

    return props.delta > 0 ? 'text-success' : 'text-danger';
});

const deltaIcon = computed(() => {
    if (props.delta === null || props.delta === 0) {
        return Minus;
    }

    return props.delta > 0 ? ArrowUpRight : ArrowDownRight;
});
</script>

<template>
    <Card class="@container relative gap-0 overflow-hidden py-0">
        <span
            class="absolute inset-y-0 left-0 w-[3px]"
            :class="accent[tone]"
            aria-hidden="true"
        />
        <CardContent class="flex flex-col gap-3 p-5">
            <div class="flex items-start justify-between gap-3">
                <p class="eyebrow">{{ label }}</p>
                <span
                    v-if="icon"
                    class="grid size-8 shrink-0 place-items-center rounded-lg"
                    :class="chip[tone]"
                >
                    <component :is="icon" class="size-4" />
                </span>
            </div>
            <Skeleton v-if="loading" class="h-8 w-32" />
            <p
                v-else
                class="figure text-[clamp(0.95rem,8.2cqw,1.5rem)] leading-none font-semibold tracking-tight whitespace-nowrap"
            >
                {{ value }}
            </p>
            <div
                v-if="delta !== null || hint"
                class="flex items-center gap-2 text-xs"
            >
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
