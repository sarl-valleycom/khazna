<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
};

defineProps<Props>();
</script>

<template>
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0">
            <h1 class="truncate text-[1.7rem] leading-tight font-semibold">
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-1 max-w-2xl text-sm text-balance text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
