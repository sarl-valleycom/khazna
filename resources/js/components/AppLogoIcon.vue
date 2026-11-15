<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- Khazna mark: the dial of a vault -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <circle
            cx="16"
            cy="16"
            r="13"
            stroke="currentColor"
            stroke-width="2.4"
        />
        <circle
            cx="16"
            cy="16"
            r="9"
            stroke="currentColor"
            stroke-width="2.2"
            stroke-linecap="round"
            stroke-dasharray="1.2 5.45"
            opacity="0.85"
        />
        <circle cx="16" cy="16" r="4.6" fill="currentColor" />
        <path
            d="M16 16 22.2 9.8"
            stroke="currentColor"
            stroke-width="2.4"
            stroke-linecap="round"
        />
    </svg>
</template>
