<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import DueActions from '@/components/dues/DueActions.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate, formatMoney } from '@/lib/format';

type Props = {
    due: App.Data.DueRowData;
    symbol: string;
    showClient?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showClient: false,
});

const tone = computed(() =>
    props.due.isOverdue ? 'overdue' : props.due.status,
);
const label = computed(() =>
    props.due.isOverdue ? 'Overdue' : props.due.statusLabel,
);
</script>

<template>
    <div
        class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-medium">{{ props.due.kindLabel }}</p>
                <StatusBadge :status="tone" :labels="{ [tone]: label }" />
                <span
                    v-if="
                        props.due.amountPaidCents > 0 &&
                        props.due.status !== 'paid' &&
                        props.due.status !== 'canceled'
                    "
                    class="text-xs text-muted-foreground"
                >
                    partially paid
                </span>
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                Due {{ formatDate(props.due.dueOn) }}
                <template v-if="props.showClient">
                    ·
                    <Link
                        :href="`/clients/${props.due.clientId}`"
                        class="hover:underline"
                        >{{ props.due.clientName }}</Link
                    >
                </template>
                <template v-if="props.due.description">
                    · {{ props.due.description }}
                </template>
            </p>
        </div>

        <div
            class="flex flex-wrap items-center justify-between gap-3 sm:justify-end"
        >
            <div class="text-right">
                <MoneyText
                    :cents="props.due.amountDueCents"
                    :symbol="props.symbol"
                    :direction="props.due.direction"
                />
                <p
                    v-if="
                        props.due.amountPaidCents > 0 &&
                        props.due.status !== 'paid' &&
                        props.due.status !== 'canceled'
                    "
                    class="text-xs text-muted-foreground"
                >
                    {{ formatMoney(props.due.remainingCents, props.symbol) }}
                    remaining
                </p>
                <p
                    v-if="props.due.creditCents > 0"
                    class="text-xs text-emerald-700 dark:text-emerald-400"
                >
                    {{ formatMoney(props.due.creditCents, props.symbol) }}
                    credit
                </p>
            </div>
            <DueActions :due="props.due" />
        </div>
    </div>
</template>
