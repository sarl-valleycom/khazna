<script setup lang="ts">
import { ref } from 'vue';
import { Paperclip } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import ReversePaymentDialog from '@/components/dues/ReversePaymentDialog.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate } from '@/lib/format';

type Props = {
    payment: App.Data.PaymentRowData;
    symbol: string;
};

const props = defineProps<Props>();
const reverseOpen = ref(false);
</script>

<template>
    <div
        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="font-medium"
                    :class="
                        props.payment.isReversed && 'line-through opacity-60'
                    "
                >
                    <MoneyText
                        :cents="props.payment.amountCents"
                        :symbol="props.symbol"
                        direction="in"
                    />
                </span>
                <StatusBadge
                    v-if="props.payment.isReversed"
                    status="canceled"
                    :labels="{ canceled: 'Reversed' }"
                />
            </div>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ formatDate(props.payment.paidOn) }} ·
                {{ props.payment.methodLabel }} →
                {{ props.payment.accountName }} · {{ props.payment.dueLabel }}
                <template v-if="props.payment.reference">
                    · Ref {{ props.payment.reference }}
                </template>
                <template v-if="props.payment.recordedBy">
                    · by {{ props.payment.recordedBy }}
                </template>
            </p>
            <p
                v-if="props.payment.isReversed && props.payment.reversalReason"
                class="mt-1 text-xs text-muted-foreground"
            >
                Reversed: {{ props.payment.reversalReason }}
            </p>
        </div>
        <div class="flex items-center gap-1">
            <Button
                v-if="props.payment.hasReceipt"
                variant="ghost"
                size="sm"
                as-child
            >
                <a
                    :href="`/payments/${props.payment.id}/receipt`"
                    target="_blank"
                    rel="noopener"
                >
                    <Paperclip class="mr-1 size-4" /> Receipt
                </a>
            </Button>
            <Button
                v-if="props.payment.canReverse"
                variant="ghost"
                size="sm"
                class="text-destructive"
                @click="reverseOpen = true"
            >
                Reverse
            </Button>
        </div>
        <ReversePaymentDialog
            v-model:open="reverseOpen"
            :payment="props.payment"
        />
    </div>
</template>
