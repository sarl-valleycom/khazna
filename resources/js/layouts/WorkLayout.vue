<script setup lang="ts">
import Toast from '@/components/kit/Toast.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="min-h-svh bg-background text-foreground">
        <main class="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4 sm:p-6">
            <slot />
        </main>
        <Toast />
        <Toaster />
    </div>
</template>
