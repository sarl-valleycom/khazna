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
        class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
    >
        <div class="rounded-full bg-muted p-3 text-muted-foreground">
            <Inbox class="size-5" />
        </div>
        <div>
            <p class="font-medium">{{ title }}</p>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action">
            <slot name="action" />
        </div>
    </div>
</template>
