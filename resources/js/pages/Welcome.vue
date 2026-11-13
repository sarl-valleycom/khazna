<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3";
import AppLogoIcon from "@/components/AppLogoIcon.vue";
import { dashboard, login } from "@/routes";
</script>

<template>
    <Head title="Khazna" />

    <div
        class="flex min-h-svh flex-col items-center justify-center gap-8 bg-background p-6"
    >
        <div class="flex flex-col items-center gap-4 text-center">
            <div
                class="flex size-14 items-center justify-center rounded-lg bg-primary text-primary-foreground"
            >
                <AppLogoIcon class="size-9 fill-current" />
            </div>
            <div class="space-y-2">
                <h1 class="text-2xl font-semibold">Khazna</h1>
                <p class="max-w-md text-sm text-muted-foreground">
                    Receivables, money out and payroll for SAASCOM.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            <Link
                v-if="$page.props.auth.user"
                :href="dashboard()"
                class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                Open dashboard
            </Link>
            <Link
                v-else
                :href="login()"
                class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                Log in
            </Link>
        </div>
    </div>
</template>
