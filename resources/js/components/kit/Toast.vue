<script setup lang="ts">
import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

type Flash = {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
};

const page = usePage<{ flash?: Flash }>();

function show(flash: Flash | undefined) {
    if (!flash) {
        return;
    }

    if (flash.success) {
        toast.success(flash.success);
    }

    if (flash.error) {
        toast.error(flash.error);
    }

    if (flash.warning) {
        toast.warning(flash.warning);
    }

    if (flash.info) {
        toast.info(flash.info);
    }
}

onMounted(() => show(page.props.flash));

watch(
    () => page.props.flash,
    (flash) => show(flash as Flash | undefined),
    { deep: true },
);
</script>

<template>
    <span class="hidden" aria-hidden="true" />
</template>
