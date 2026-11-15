<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney } from '@/lib/format';

type Props = {
    cents: number;
    formatted?: string;
    direction?: 'in' | 'out' | 'neutral';
    symbol?: string;
    showSign?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    direction: 'neutral',
    symbol: 'DA',
    showSign: false,
});

const text = computed(
    () => props.formatted ?? formatMoney(props.cents, props.symbol),
);

const signedText = computed(() => {
    if (!props.showSign || props.cents === 0) {
        return text.value;
    }

    const sign = props.cents > 0 ? '+' : '−';

    return `${sign}${text.value}`;
});

const tone = computed(() => {
    if (props.direction === 'in') {
        return 'text-success';
    }

    if (props.direction === 'out') {
        return 'text-danger';
    }

    return '';
});
</script>

<template>
    <span class="figure font-medium" :class="tone">{{ signedText }}</span>
</template>
