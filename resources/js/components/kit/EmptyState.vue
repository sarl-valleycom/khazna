<script setup lang="ts">
import { Inbox } from '@lucide/vue';

type Props = {
    title: string;
    description?: string;
};

withDefaults(defineProps<Props>(), {
    description: undefined,
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-4 px-6 py-14 text-center"
    >
        <div
            class="relative grid size-14 place-items-center rounded-2xl border bg-gradient-to-b from-card to-muted text-muted-foreground shadow-card"
        >
            <slot name="icon">
                <Inbox class="size-6" />
            </slot>
            <span
                class="absolute -top-1 -right-1 size-3 rounded-full bg-gold ring-4 ring-card"
                aria-hidden="true"
            />
        </div>
        <div class="max-w-sm">
            <p class="text-base font-semibold tracking-tight">{{ title }}</p>
            <p
                v-if="description"
                class="mt-1 text-sm text-balance text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action">
            <slot name="action" />
        </div>
    </div>
</template>
