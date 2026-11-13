<script setup lang="ts">
import { ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import { formatMoney, parseMoneyInput } from '@/lib/format';

type Props = {
    id?: string;
    symbol?: string;
    disabled?: boolean;
    placeholder?: string;
};

const props = withDefaults(defineProps<Props>(), {
    symbol: 'DA',
    placeholder: '0.00',
});

const model = defineModel<number | null>({ default: null });
const emits = defineEmits<{ invalid: [message: string] }>();

const display = ref('');
const focused = ref(false);

function toDisplay(cents: number | null): string {
    if (cents === null) {
        return '';
    }

    return formatMoney(cents, props.symbol).replace(` ${props.symbol}`, '');
}

watch(
    model,
    (value) => {
        if (!focused.value) {
            display.value = toDisplay(value);
        }
    },
    { immediate: true },
);

function onInput(event: Event) {
    const value = (event.target as HTMLInputElement).value;
    display.value = value;

    const parsed = parseMoneyInput(value);

    if (parsed !== null && Number.isNaN(parsed)) {
        emits('invalid', 'Enter a valid amount.');
        return;
    }

    model.value = parsed;
}

function onFocus() {
    focused.value = true;

    if (model.value !== null) {
        display.value = (model.value / 100).toFixed(2).replace('.', ',');
    }
}

function onBlur() {
    focused.value = false;
    display.value = toDisplay(model.value);
}
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :model-value="display"
            :disabled="disabled"
            :placeholder="placeholder"
            inputmode="decimal"
            autocomplete="off"
            class="pr-10 text-right tabular-nums"
            @input="onInput"
            @focus="onFocus"
            @blur="onBlur"
        />
        <span
            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground"
        >
            {{ symbol }}
        </span>
    </div>
</template>
