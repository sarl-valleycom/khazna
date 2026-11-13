<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Deferred } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import UserText from '@/components/UserText.vue';
import { formatDate, formatMoney } from '@/lib/format';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'UI kit', href: '/dev/ui' },
        ],
    },
});

const props = defineProps<{
    page: App.Data.DevUiData;
    generatedAt?: string;
}>();
</script>

<template>
    <Head title="UI kit" />

    <AppLayout>
        <div class="flex flex-col gap-6 p-4">
            <div>
                <h1 class="text-2xl font-semibold">{{ props.page.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    TypeScript types are generated from the PHP Data classes.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Arabic name</p>
                    <p class="text-lg font-medium">
                        <UserText :text="props.page.clientNameArabic" />
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Formatted money</p>
                    <p class="text-lg font-medium">
                        {{ formatMoney(props.page.amountCents) }}
                    </p>
                </div>
                <div class="rounded-xl border p-4">
                    <p class="text-sm text-muted-foreground">Formatted date</p>
                    <p class="text-lg font-medium">
                        {{ formatDate(props.page.date) }}
                    </p>
                </div>
            </div>

            <Deferred data="generatedAt">
                <template #fallback>
                    <p class="text-sm text-muted-foreground">
                        Loading a deferred prop…
                    </p>
                </template>
                <p class="text-sm text-muted-foreground">
                    Deferred prop loaded at {{ props.generatedAt }}
                </p>
            </Deferred>
        </div>
    </AppLayout>
</template>
