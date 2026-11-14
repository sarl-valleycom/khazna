<script setup lang="ts">
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import FormDialog from '@/components/kit/FormDialog.vue';

type Props = {
    contractId: number;
    status: App.Enums.ContractStatus;
};

const props = defineProps<Props>();

const open = ref(false);
const target = ref<'paused' | 'canceled'>('paused');
const form = useForm({
    status: 'paused',
    reason: '',
});

const asking = computed(() => (target.value === 'paused' ? 'Pause' : 'Cancel'));

function ask(status: 'paused' | 'canceled') {
    target.value = status;
    form.status = status;
    form.reason = '';
    form.clearErrors();
    open.value = true;
}

function resume() {
    form.status = 'active';
    form.reason = '';
    form.clearErrors();

    form.patch(`/contracts/${props.contractId}/status`, {
        preserveScroll: true,
    });
}

function submit() {
    form.patch(`/contracts/${props.contractId}/status`, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <Button
            v-if="props.status === 'active'"
            variant="outline"
            size="sm"
            :disabled="form.processing"
            @click="ask('paused')"
        >
            Pause
        </Button>
        <Button
            v-if="props.status === 'paused'"
            variant="outline"
            size="sm"
            :disabled="form.processing"
            @click="resume"
        >
            Resume
        </Button>
        <Button
            v-if="props.status !== 'canceled'"
            variant="ghost"
            size="sm"
            class="text-destructive"
            :disabled="form.processing"
            @click="ask('canceled')"
        >
            Cancel
        </Button>
    </div>

    <FormDialog
        v-model:open="open"
        :title="`${asking} contract`"
        :description="
            asking === 'Pause'
                ? 'Pausing stops new dues. Open future dues are canceled; paid dues are unchanged.'
                : 'Cancelling ends the contract. Open future dues are canceled; paid dues are unchanged.'
        "
        :submit-label="asking"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="contract-status-reason">Reason</Label>
            <textarea
                id="contract-status-reason"
                v-model="form.reason"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>
</template>
