<script setup lang="ts">
import { computed, ref } from 'vue';
import { parseDate, type DateValue } from '@internationalized/date';
import { CalendarIcon } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDate, toCalendarDate } from '@/lib/format';

type Props = {
    id?: string;
    placeholder?: string;
    disabled?: boolean;
    min?: string;
    max?: string;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Pick a date',
});

const model = defineModel<string | null>({ default: null });
const open = ref(false);

const value = computed<DateValue | undefined>(() => {
    if (!model.value) {
        return undefined;
    }

    try {
        return parseDate(model.value);
    } catch {
        return undefined;
    }
});

function safeParse(text: string | undefined): DateValue | undefined {
    if (!text) {
        return undefined;
    }

    try {
        return parseDate(text);
    } catch {
        return undefined;
    }
}

const minValue = computed(() => safeParse(props.min));
const maxValue = computed(() => safeParse(props.max));

// "Today" is only offered when it falls inside min/max (ISO dates compare as text).
const todayAllowed = computed(() => {
    const today = toCalendarDate(new Date());

    return (
        (!minValue.value || today >= minValue.value.toString()) &&
        (!maxValue.value || today <= maxValue.value.toString())
    );
});

function selectDate(date: DateValue | undefined) {
    model.value = date ? date.toString() : null;
    open.value = false;
}

function selectToday() {
    if (!todayAllowed.value) {
        return;
    }

    // Calendar dates are Algiers dates, whatever the browser timezone is.
    model.value = toCalendarDate(new Date());
    open.value = false;
}

function clear() {
    model.value = null;
    open.value = false;
}
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="id"
                type="button"
                variant="outline"
                :disabled="disabled"
                class="w-full justify-start text-left font-normal"
                :class="!model && 'text-muted-foreground'"
            >
                <CalendarIcon class="mr-2 size-4" />
                {{ model ? formatDate(model) : placeholder }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start">
            <Calendar
                :model-value="value"
                :min-value="minValue"
                :max-value="maxValue"
                @update:model-value="selectDate"
            />
            <div class="flex items-center justify-between border-t p-2">
                <Button type="button" variant="ghost" size="sm" @click="clear">
                    Clear
                </Button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    :disabled="!todayAllowed"
                    @click="selectToday"
                >
                    Today
                </Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
