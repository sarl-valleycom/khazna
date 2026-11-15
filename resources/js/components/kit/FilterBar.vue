<script setup lang="ts">
import { ref, watch } from 'vue';
import { Search } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DateInput from '@/components/kit/DateInput.vue';
import type {
    DateRange,
    FilterTab,
    SelectFilter,
} from '@/components/kit/types';
import { updateQuery } from '@/lib/query';

type Props = {
    tabs?: FilterTab[];
    activeTab?: string;
    search?: string;
    searchPlaceholder?: string;
    select?: SelectFilter;
    dateRange?: DateRange;
    reloadProps?: string[];
    /** Show the search box. Pages without a text search turn it off. */
    searchable?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    tabs: () => [],
    activeTab: '',
    search: '',
    searchPlaceholder: 'Search…',
    select: undefined,
    dateRange: undefined,
    reloadProps: () => [],
    searchable: true,
});

const emits = defineEmits<{
    'update:activeTab': [string];
    'update:search': [string];
    'update:select': [string | null];
    'update:dateRange': [DateRange];
}>();

const search = ref(props.search);
const from = ref(props.dateRange?.from ?? null);
const to = ref(props.dateRange?.to ?? null);

// True from the first keystroke until the debounced search is sent. While it
// is set, an echoed value from an earlier request must not overwrite the text.
let typing = false;

watch(
    () => props.search,
    (value) => {
        if (!typing) {
            search.value = value;
        }
    },
);

watch(
    () => props.dateRange,
    (value) => {
        from.value = value?.from ?? null;
        to.value = value?.to ?? null;
    },
    { deep: true },
);

function apply(params: Record<string, string | null>) {
    updateQuery(params, props.reloadProps);
}

const sendSearch = useDebounceFn(() => {
    typing = false;
    emits('update:search', search.value);
    apply({ q: search.value || null, page: null });
}, 350);

function commitSearch() {
    typing = true;
    sendSearch();
}

function chooseTab(value: string) {
    emits('update:activeTab', value);
    apply({ tab: value || null, page: null });
}

function chooseSelect(value: string) {
    const normalized = value === 'all' ? null : value;
    emits('update:select', normalized);
    apply({ [props.select?.key ?? 'filter']: normalized, page: null });
}

function commitDateRange() {
    const range = { from: from.value, to: to.value };
    emits('update:dateRange', range);
    apply({
        from: range.from || null,
        to: range.to || null,
        page: null,
    });
}
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-xl border bg-card p-3 lg:flex-row lg:items-center lg:justify-between"
    >
        <div class="flex flex-wrap items-center gap-1" role="tablist">
            <button
                v-for="tab in tabs"
                :key="tab.value"
                type="button"
                role="tab"
                :aria-selected="activeTab === tab.value"
                class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                :class="
                    activeTab === tab.value
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                @click="chooseTab(tab.value)"
            >
                {{ tab.label }}
                <span
                    v-if="tab.count !== undefined"
                    class="rounded-full px-1.5 py-0.5 text-xs tabular-nums"
                    :class="
                        activeTab === tab.value
                            ? 'bg-primary-foreground/20'
                            : 'bg-muted'
                    "
                >
                    {{ tab.count }}
                </span>
            </button>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div v-if="searchable" class="relative min-w-48 flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    :placeholder="searchPlaceholder"
                    class="pl-8"
                    @input="commitSearch"
                />
            </div>

            <Select
                v-if="select"
                :model-value="select.value ?? 'all'"
                @update:model-value="(value) => chooseSelect(String(value))"
            >
                <SelectTrigger class="w-full sm:w-auto sm:min-w-44">
                    <SelectValue :placeholder="select.label" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">{{ select.label }}: all</SelectItem>
                    <SelectItem
                        v-for="option in select.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <div v-if="dateRange" class="flex items-center gap-2">
                <div class="w-36">
                    <DateInput
                        v-model="from"
                        placeholder="From"
                        :max="to ?? undefined"
                        @update:model-value="commitDateRange"
                    />
                </div>
                <span class="text-muted-foreground">–</span>
                <div class="w-36">
                    <DateInput
                        v-model="to"
                        placeholder="To"
                        :min="from ?? undefined"
                        @update:model-value="commitDateRange"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
