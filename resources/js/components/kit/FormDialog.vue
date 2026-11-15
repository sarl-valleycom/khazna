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

type Props = {
    title: string;
    description?: string;
    submitLabel?: string;
    cancelLabel?: string;
    processing?: boolean;
    errors?: Record<string, string>;
    /** Fields whose error the form shows next to the field itself, so it is not repeated below. */
    inlineFields?: string[];
    submitDisabled?: boolean;
    wide?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    description: undefined,
    submitLabel: 'Save',
    cancelLabel: 'Cancel',
    processing: false,
    errors: () => ({}),
    inlineFields: () => [],
    submitDisabled: false,
    wide: false,
});

const open = defineModel<boolean>('open', { default: false });
const emits = defineEmits<{ submit: [] }>();

// A message about what the user has since changed is stale: hide it on the first edit
// and show errors again when a new response brings them back.
const edited = ref(false);

watch(
    () => props.errors,
    () => (edited.value = false),
    { deep: true },
);
watch(
    () => props.processing,
    (processing) => {
        if (processing) {
            edited.value = false;
        }
    },
);

const messages = computed(() =>
    edited.value
        ? []
        : Object.entries(props.errors).filter(
              ([field]) => !props.inlineFields.includes(field),
          ),
);

function onSubmit() {
    if (props.processing || props.submitDisabled) {
        return;
    }

    emits('submit');
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent :class="wide ? 'sm:max-w-2xl' : 'sm:max-w-lg'">
            <form
                class="space-y-4"
                @submit.prevent="onSubmit"
                @input="edited = true"
                @change="edited = true"
            >
                <DialogHeader>
                    <DialogTitle>{{ title }}</DialogTitle>
                    <DialogDescription v-if="description">
                        {{ description }}
                    </DialogDescription>
                </DialogHeader>

                <slot />

                <p
                    v-for="[field, message] in messages"
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
