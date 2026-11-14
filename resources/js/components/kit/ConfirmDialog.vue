<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

type Props = {
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    processing?: boolean;
    destructive?: boolean;
    reasonRequired?: boolean;
    reasonLabel?: string;
};

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    processing: false,
    destructive: false,
    reasonRequired: false,
    reasonLabel: 'Reason',
});

const open = defineModel<boolean>('open', { default: false });
const reason = ref('');
const emits = defineEmits<{ confirm: [reason: string | null] }>();

const canConfirm = computed(
    () =>
        !props.processing &&
        (!props.reasonRequired || reason.value.trim() !== ''),
);

watch(open, (value) => {
    if (!value) {
        reason.value = '';
    }
});

function confirm() {
    if (!canConfirm.value) {
        return;
    }

    emits('confirm', reason.value.trim() || null);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="reasonRequired" class="space-y-2">
                <Label for="confirm-reason">{{ reasonLabel }}</Label>
                <textarea
                    id="confirm-reason"
                    v-model="reason"
                    rows="3"
                    class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :placeholder="`${reasonLabel} (required)`"
                />
                <p
                    v-if="reason.trim() === ''"
                    class="text-xs text-muted-foreground"
                >
                    A reason is required before confirming.
                </p>
            </div>

            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="processing"
                    @click="open = false"
                >
                    {{ cancelLabel }}
                </Button>
                <Button
                    type="button"
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="!canConfirm"
                    @click="confirm"
                >
                    {{ processing ? 'Working…' : confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
