<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-xl bg-gradient-to-br from-gold to-[oklch(0.7_0.14_70)] text-sidebar-primary-foreground shadow-[inset_0_1px_0_oklch(1_0_0/0.4),0_2px_6px_oklch(0.1_0.05_258/0.35)]"
    >
        <AppLogoIcon class="size-6" />
    </div>
    <div class="ms-1 grid flex-1 text-start">
        <span
            class="truncate text-[0.95rem] leading-tight font-semibold tracking-tight"
            >{{ name }}</span
        >
        <span
            class="truncate text-[0.7rem] leading-tight text-sidebar-foreground/60"
            ><span lang="ar" dir="rtl">خزنة</span> · Treasury</span
        >
    </div>
</template>
