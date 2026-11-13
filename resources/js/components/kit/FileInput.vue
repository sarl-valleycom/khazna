<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { FileImage, Upload, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';

type Props = {
    accept?: string;
    maxSizeMb?: number;
    disabled?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    accept: 'image/*,.pdf',
    maxSizeMb: 5,
    disabled: false,
});

const model = defineModel<File | null>({ default: null });
const emits = defineEmits<{ invalid: [message: string] }>();

const input = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);
const name = ref<string | null>(null);

function matchesAccept(file: File): boolean {
    if (!props.accept) {
        return true;
    }

    return props.accept.split(',').some((rule) => {
        const trimmed = rule.trim().toLowerCase();

        if (trimmed.startsWith('.')) {
            return file.name.toLowerCase().endsWith(trimmed);
        }

        if (trimmed.endsWith('/*')) {
            return file.type.startsWith(trimmed.slice(0, -1));
        }

        return file.type === trimmed;
    });
}

function resetPreview() {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }

    preview.value = null;
    name.value = null;
}

watch(model, (file) => {
    resetPreview();

    if (!file) {
        return;
    }

    name.value = file.name;

    if (file.type.startsWith('image/')) {
        preview.value = URL.createObjectURL(file);
    }
});

onBeforeUnmount(resetPreview);

function onChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    if (!file) {
        model.value = null;
        return;
    }

    if (!matchesAccept(file)) {
        emits('invalid', `Choose a file of type ${props.accept}.`);
        (event.target as HTMLInputElement).value = '';
        model.value = null;
        return;
    }

    if (file.size > props.maxSizeMb * 1024 * 1024) {
        emits('invalid', `The file must be ${props.maxSizeMb} MB or smaller.`);
        (event.target as HTMLInputElement).value = '';
        model.value = null;
        return;
    }

    model.value = file;
}

function clear() {
    model.value = null;

    if (input.value) {
        input.value.value = '';
    }
}
</script>

<template>
    <div class="space-y-2">
        <label
            class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed p-6 text-center transition-colors hover:bg-muted/50"
            :class="disabled && 'pointer-events-none opacity-50'"
        >
            <input
                ref="input"
                type="file"
                class="sr-only"
                :accept="accept"
                :disabled="disabled"
                @change="onChange"
            />
            <img
                v-if="preview"
                :src="preview"
                alt="Selected file preview"
                class="max-h-40 rounded-lg object-contain"
            />
            <template v-else>
                <FileImage
                    v-if="accept.includes('image')"
                    class="size-8 text-muted-foreground"
                />
                <Upload v-else class="size-8 text-muted-foreground" />
                <span class="text-sm font-medium"
                    >Choose a file or drop it here</span
                >
                <span class="text-xs text-muted-foreground">
                    {{ accept }} · up to {{ maxSizeMb }} MB
                </span>
            </template>
        </label>

        <div
            v-if="name"
            class="flex items-center justify-between gap-2 text-sm"
        >
            <span class="truncate text-muted-foreground">{{ name }}</span>
            <Button type="button" variant="ghost" size="sm" @click="clear">
                <X class="mr-1 size-4" />
                Remove
            </Button>
        </div>
    </div>
</template>
