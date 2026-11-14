<script setup lang="ts">
import { computed } from 'vue';
import { Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DateInput from '@/components/kit/DateInput.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import { formatMoney } from '@/lib/format';

export type MilestoneRow = {
    label: string;
    mode: 'percent' | 'amount';
    value: number | null;
    trigger: 'date' | 'delivery';
    due_on: string | null;
};

type Props = {
    /** Contract total in centimes, to show the running check. */
    totalCents: number | null;
    symbol: string;
    errors?: Record<string, string>;
};

const props = defineProps<Props>();
const rows = defineModel<MilestoneRow[]>({ default: () => [] });

// One mode for the whole plan: the server refuses a mix.
const mode = computed({
    get: () => rows.value[0]?.mode ?? 'percent',
    set: (value: 'percent' | 'amount') => {
        rows.value = rows.value.map((row) => ({
            ...row,
            mode: value,
            value: null,
        }));
    },
});

function add() {
    rows.value = [
        ...rows.value,
        {
            label: '',
            mode: mode.value,
            value: null,
            trigger: 'delivery',
            due_on: null,
        },
    ];
}

function remove(index: number) {
    rows.value = rows.value.filter((_, i) => i !== index);
}

const sum = computed(() =>
    rows.value.reduce((total, row) => total + (row.value ?? 0), 0),
);

const check = computed(() => {
    if (rows.value.length === 0) {
        return { ok: false, text: 'Add at least one milestone.' };
    }

    if (mode.value === 'percent') {
        const ok = Math.abs(sum.value - 100) < 0.0005;

        return {
            ok,
            text: ok
                ? 'Adds up to 100%.'
                : `Adds up to ${Number(sum.value.toFixed(3))}%, it must be 100%.`,
        };
    }

    const ok = props.totalCents !== null && sum.value === props.totalCents;

    return {
        ok,
        text: ok
            ? 'Adds up to the contract amount.'
            : `Adds up to ${formatMoney(sum.value, props.symbol)}, it must equal ${formatMoney(props.totalCents ?? 0, props.symbol)}.`,
    };
});
</script>

<template>
    <div class="space-y-3 rounded-md border p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <Label>Milestones</Label>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-muted-foreground">Split by</span>
                <Select v-model="mode">
                    <SelectTrigger
                        class="h-8 w-32"
                        aria-label="Split milestones by"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="percent">Percentage</SelectItem>
                        <SelectItem value="amount">Amount</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div
            v-for="(row, index) in rows"
            :key="index"
            class="grid gap-2 rounded-md bg-muted/40 p-2 sm:grid-cols-[1fr_8rem_9rem_auto]"
        >
            <Input
                v-model="row.label"
                :aria-label="`Milestone ${index + 1} label`"
                placeholder="Deposit, beta, launch…"
            />
            <div>
                <Input
                    v-if="mode === 'percent'"
                    :model-value="row.value ?? undefined"
                    @update:model-value="
                        (v) => (row.value = v === '' ? null : Number(v))
                    "
                    type="number"
                    step="0.001"
                    min="0"
                    max="100"
                    :aria-label="`Milestone ${index + 1} percentage`"
                    placeholder="%"
                />
                <MoneyInput v-else v-model="row.value" :symbol="props.symbol" />
            </div>
            <Select v-model="row.trigger">
                <SelectTrigger :aria-label="`Milestone ${index + 1} trigger`">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="date">On a date</SelectItem>
                    <SelectItem value="delivery">On delivery</SelectItem>
                </SelectContent>
            </Select>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                :aria-label="`Remove milestone ${index + 1}`"
                @click="remove(index)"
            >
                <Trash2 class="size-4" />
            </Button>
            <div v-if="row.trigger === 'date'" class="sm:col-span-4">
                <DateInput v-model="row.due_on" placeholder="Due date" />
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <Button type="button" variant="outline" size="sm" @click="add">
                <Plus class="mr-1 size-4" /> Add milestone
            </Button>
            <p
                class="text-xs"
                :class="check.ok ? 'text-emerald-700' : 'text-destructive'"
                role="status"
            >
                {{ check.text }}
            </p>
        </div>
        <p v-if="props.errors?.milestones" class="text-sm text-destructive">
            {{ props.errors.milestones }}
        </p>
    </div>
</template>
