<script setup lang="ts">
import { computed } from 'vue';
import { Head, usePoll } from '@inertiajs/vue3';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Who is working', href: '/attendance/presence' },
        ],
    },
});

type Props = {
    presence: App.Data.AttendancePresenceData[];
};

const props = defineProps<Props>();

usePoll(30000, { only: ['presence'] });

const counts = computed(() => ({
    working: props.presence.filter((row) => row.state === 'working').length,
    away: props.presence.filter((row) => row.state === 'away').length,
    offline: props.presence.filter((row) => row.state === 'offline').length,
}));

function tone(state: string): string {
    return state === 'working'
        ? 'active'
        : state === 'away'
          ? 'warning'
          : 'inactive';
}
</script>

<template>
    <Head title="Who is working" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Who is working now"
            description="Refreshes every 30 seconds. Working means checked in, away means on a break."
        />

        <AttendanceNav />

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Working</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ counts.working }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Away</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ counts.away }}
                </p>
            </div>
            <div class="rounded-xl border bg-card p-4">
                <p class="text-xs text-muted-foreground">Offline</p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ counts.offline }}
                </p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card">
            <EmptyState
                v-if="props.presence.length === 0"
                title="No active employees"
                description="Add active employees to see who is working."
            />
            <div v-else class="divide-y">
                <div
                    v-for="row in props.presence"
                    :key="row.employeeId"
                    class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="font-medium">{{ row.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ row.officeName }}
                            <template v-if="row.sinceLabel">
                                · {{ row.sinceLabel }}
                            </template>
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <p class="text-sm tabular-nums text-muted-foreground">
                            Today {{ row.todayFormatted }}
                        </p>
                        <StatusBadge
                            :status="tone(row.state)"
                            :labels="{
                                active: 'Working',
                                warning: 'Away',
                                inactive: 'Offline',
                            }"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
