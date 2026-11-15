<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { Button } from '@/components/ui/button';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Monthly summary', href: '/attendance/summary' },
        ],
    },
});

type Props = {
    month: string;
    monthLabel: string;
    previousMonth: string;
    nextMonth: string | null;
    rows: App.Data.MonthlySummaryRowData[];
};

const props = defineProps<Props>();

function goTo(month: string) {
    router.get(
        '/attendance/summary',
        { month },
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <Head title="Monthly summary" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Monthly summary"
            description="Hours worked against what each person was expected to work. It is information only: it never changes a salary. For the current month the target runs through today."
        />

        <AttendanceNav />

        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="icon-sm"
                aria-label="Previous month"
                @click="goTo(props.previousMonth)"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <span class="min-w-32 text-center text-sm font-medium">
                {{ props.monthLabel }}
            </span>
            <Button
                variant="outline"
                size="icon-sm"
                aria-label="Next month"
                :disabled="props.nextMonth === null"
                @click="props.nextMonth && goTo(props.nextMonth)"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <div class="overflow-x-auto rounded-xl border bg-card">
            <EmptyState
                v-if="props.rows.length === 0"
                title="No employees"
                description="Active employees appear here."
            />
            <table v-else class="w-full text-sm">
                <thead class="border-b text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 font-medium">Employee</th>
                        <th class="p-3 text-right font-medium">Worked</th>
                        <th class="p-3 text-right font-medium">Expected</th>
                        <th class="p-3 text-right font-medium">To make up</th>
                        <th class="p-3 text-right font-medium">Extra</th>
                        <th class="p-3 text-right font-medium">Days worked</th>
                        <th class="p-3 text-right font-medium">Days off</th>
                        <th class="p-3 font-medium">Sessions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="row in props.rows" :key="row.employeeId">
                        <td class="p-3">
                            <p class="font-medium">{{ row.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ row.officeName }}
                            </p>
                            <p
                                v-if="row.notChecked > 0"
                                class="text-xs text-amber-600"
                            >
                                {{ row.notChecked }}
                                {{
                                    row.notChecked === 1
                                        ? 'session was'
                                        : 'sessions were'
                                }}
                                closed automatically and not yet checked
                            </p>
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ row.workedFormatted }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ row.expectedFormatted }}
                        </td>
                        <td
                            class="p-3 text-right font-medium tabular-nums"
                            :class="row.toMakeUpMinutes > 0 && 'text-amber-600'"
                        >
                            {{ row.toMakeUpFormatted }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ row.extraFormatted }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ row.daysWorked }}
                        </td>
                        <td class="p-3 text-right tabular-nums">
                            {{ row.daysOff }}
                        </td>
                        <td class="p-3">
                            <Link
                                :href="row.sessionsUrl"
                                class="text-primary underline-offset-4 hover:underline"
                            >
                                View
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
