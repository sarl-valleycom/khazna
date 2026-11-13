<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    title: string;
    description?: string;
    submitLabel?: string;
    cancelLabel?: string;
    processing?: boolean;
    errors?: Record<string, string>;
    submitDisabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    submitLabel: 'Save',
    cancelLabel: 'Cancel',
    processing: false,
    errors: () => ({}),
    submitDisabled: false,
});

const open = defineModel<boolean>('open', { default: false });
const emits = defineEmits<{ submit: [] }>();

function onSubmit() {
    if (props.processing || props.submitDisabled) {
        return;
    }

    emits('submit');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="space-y-4" @submit.prevent="onSubmit">
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription v-if="description">
                        {{ description }}
                    </DialogDescription>
                </DialogHeader>

                <slot />

                <p
                    v-for="(message, field) in errors"
                    :key="field"
                    class="text-sm text-destructive"
                >
                    {{ message }}
                </p>

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
                        type="submit"
                        :disabled="processing || submitDisabled"
                    >
                        {{ processing ? 'Working…' : submitLabel }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
