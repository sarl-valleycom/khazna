<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const items = [
    { label: 'Sessions', href: '/attendance/sessions' },
    { label: 'Who is working', href: '/attendance/presence' },
    { label: 'Corrections', href: '/attendance/corrections' },
    { label: 'Monthly summary', href: '/attendance/summary' },
    { label: 'Holidays', href: '/attendance/holidays' },
    { label: 'Leave', href: '/attendance/leave' },
    { label: 'Settings', href: '/attendance/settings' },
];

function active(href: string): boolean {
    return page.url.split('?')[0] === href;
}
</script>

<template>
    <nav
        class="flex flex-wrap items-center gap-1 rounded-xl border bg-card p-1"
        aria-label="Attendance"
    >
        <Link
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
            :class="
                active(item.href)
                    ? 'bg-primary text-primary-foreground'
                    : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            "
        >
            {{ item.label }}
        </Link>
    </nav>
</template>
