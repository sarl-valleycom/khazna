<script setup lang="ts">
import { ref } from 'vue';
import { MessageCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import CancelDueDialog from '@/components/dues/CancelDueDialog.vue';
import PayDueDialog from '@/components/dues/PayDueDialog.vue';
import PostponeDueDialog from '@/components/dues/PostponeDueDialog.vue';
import AdjustmentDialog from '@/components/payroll/AdjustmentDialog.vue';
import { sendWhatsAppReminder } from '@/lib/reminders';
import { usePage } from '@inertiajs/vue3';

type Props = {
    due: App.Data.DueRowData;
    /** Triage keeps to the three daily actions; canceling lives on the client and contract pages. */
    showCancel?: boolean;
};

const props = withDefaults(defineProps<Props>(), { showCancel: true });
const payOpen = ref(false);
const postponeOpen = ref(false);
const cancelOpen = ref(false);
const reminding = ref(false);
const advanceOpen = ref(false);
const page = usePage<{
    payrollEmployees?: Array<{ id: number; name: string }>;
    paymentAccounts?: App.Data.PaymentAccountData[];
    payrollTypes?: Array<{ value: string; label: string }>;
}>();

async function remind() {
    reminding.value = true;

    try {
        await sendWhatsAppReminder(props.due.id);
    } finally {
        reminding.value = false;
    }
}
</script>

<template>
    <div
        class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap sm:items-center sm:gap-1"
    >
        <Button
            v-if="props.due.abilities.pay"
            size="sm"
            class="h-10 sm:h-8"
            @click="payOpen = true"
        >
            Pay
        </Button>
        <Button
            v-if="props.due.abilities.postpone"
            variant="outline"
            size="sm"
            class="h-10 sm:h-8"
            @click="postponeOpen = true"
        >
            Postpone
        </Button>
        <Button
            v-if="props.due.abilities.remind"
            variant="outline"
            size="sm"
            class="h-10 sm:h-8"
            :disabled="reminding"
            :aria-label="`Send a WhatsApp reminder to ${props.due.partyName}`"
            @click="remind"
        >
            <MessageCircle class="mr-1 size-4" /> WhatsApp
        </Button>
        <Button
            v-if="props.due.abilities.advance"
            variant="outline"
            size="sm"
            class="h-10 sm:h-8"
            @click="advanceOpen = true"
        >
            Advance
        </Button>
        <Button
            v-if="props.showCancel && props.due.abilities.cancel"
            variant="ghost"
            size="sm"
            class="h-10 text-destructive sm:h-8"
            @click="cancelOpen = true"
        >
            Cancel
        </Button>
    </div>

    <PayDueDialog v-model:open="payOpen" :due="props.due" />
    <AdjustmentDialog
        v-if="props.due.abilities.advance"
        v-model:open="advanceOpen"
        :employees="page.props.payrollEmployees ?? []"
        :accounts="page.props.paymentAccounts ?? []"
        :types="page.props.payrollTypes ?? []"
        :month="(props.due.period ?? '').slice(0, 7)"
        :employee-id="props.due.employeeId"
        type="advance"
    />
    <PostponeDueDialog v-model:open="postponeOpen" :due="props.due" />
    <CancelDueDialog v-model:open="cancelOpen" :due="props.due" />
</template>
