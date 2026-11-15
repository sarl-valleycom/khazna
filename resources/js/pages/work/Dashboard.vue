<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    dashboard: App.Data.WorkDashboardData;
};

const props = defineProps<Props>();

// The live timers count from the moment the page data was made, using the
// server clock, so a phone with a wrong clock still shows the right time.
const tick = ref(Date.now());
const loadedAt = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

watch(
    () => props.dashboard.serverNow,
    () => {
        loadedAt.value = Date.now();
        tick.value = Date.now();
    },
);

onMounted(() => {
    timer = setInterval(() => (tick.value = Date.now()), 1000);
});
onBeforeUnmount(() => clearInterval(timer));

const sinceLoadSeconds = computed(() =>
    Math.max(0, Math.floor((tick.value - loadedAt.value) / 1000)),
);

const sessionSeconds = computed(() => {
    if (!props.dashboard.sessionStartedAt) {
        return 0;
    }

    const base =
        (Date.parse(props.dashboard.serverNow) -
            Date.parse(props.dashboard.sessionStartedAt)) /
        1000;

    return Math.max(0, Math.floor(base) + sinceLoadSeconds.value);
});

const todaySeconds = computed(
    () =>
        props.dashboard.todayWorkedMinutes * 60 +
        (props.dashboard.state === 'working' ? sinceLoadSeconds.value : 0),
);

function clock(totalSeconds: number): string {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;

    return [hours, minutes, seconds]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}

function minutesText(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;

    return hours > 0
        ? `${hours}h ${String(rest).padStart(2, '0')}m`
        : `${rest}m`;
}

const todayRemaining = computed(() =>
    Math.max(
        0,
        props.dashboard.today.expectedMinutes -
            Math.floor(todaySeconds.value / 60),
    ),
);

const stateLabel = computed(
    () =>
        ({
            offline: 'Not checked in',
            working: 'Working',
            away: 'On a break',
        })[props.dashboard.state],
);

const processing = ref(false);

function post(url: string, data: Record<string, unknown> = {}) {
    router.post(url, data as never, {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}

const breakForm = useForm({ reason: '' });
const showBreak = ref(false);

function startBreak() {
    breakForm.post('/work/break', {
        preserveScroll: true,
        onSuccess: () => {
            breakForm.reset();
            showBreak.value = false;
        },
    });
}

const reportForm = useForm({ report: '', blockers: '' });

function saveReport() {
    if (!props.dashboard.reportPrompt) {
        return;
    }

    reportForm.post(
        `/work/sessions/${props.dashboard.reportPrompt.id}/report`,
        {
            preserveScroll: true,
            onSuccess: () => reportForm.reset(),
        },
    );
}

const correctionForm = useForm({ ended_at: '', note: '' });

function saveCorrection() {
    if (!props.dashboard.correction) {
        return;
    }

    correctionForm.post(
        `/work/sessions/${props.dashboard.correction.id}/correct`,
        {
            preserveScroll: true,
            onSuccess: () => correctionForm.reset(),
        },
    );
}

function leave() {
    router.post('/work/leave');
}

const periods = computed(() => [
    { key: 'week', title: 'This week', data: props.dashboard.week },
    { key: 'month', title: 'This month', data: props.dashboard.month },
]);

const textarea =
    'flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50';
</script>

<template>
    <Head title="My attendance" />

    <header class="flex items-center justify-between gap-2">
        <div>
            <p class="text-sm text-muted-foreground">Hello</p>
            <h1 class="text-xl font-semibold">{{ dashboard.name }}</h1>
        </div>
        <Button variant="outline" size="sm" @click="leave">Leave</Button>
    </header>

    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center justify-between text-base">
                <span>{{ stateLabel }}</span>
                <span
                    class="size-3 rounded-full"
                    :class="{
                        'bg-green-500': dashboard.state === 'working',
                        'bg-amber-500': dashboard.state === 'away',
                        'bg-muted-foreground/40': dashboard.state === 'offline',
                    }"
                />
            </CardTitle>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-if="dashboard.state !== 'offline'" class="space-y-1">
                <p class="text-xs text-muted-foreground">Since check-in</p>
                <p class="text-4xl font-semibold tabular-nums" aria-live="off">
                    {{ clock(sessionSeconds) }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-muted-foreground">Worked today</p>
                    <p class="text-lg font-medium tabular-nums">
                        {{ minutesText(Math.floor(todaySeconds / 60)) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        Still to do today
                    </p>
                    <p class="text-lg font-medium tabular-nums">
                        {{ minutesText(todayRemaining) }}
                        <span class="text-xs font-normal text-muted-foreground">
                            of {{ dashboard.today.expectedFormatted }}
                        </span>
                    </p>
                </div>
            </div>

            <div class="grid gap-2">
                <Button
                    v-if="dashboard.state === 'offline'"
                    class="h-14 text-lg"
                    :disabled="processing"
                    @click="post('/work/check-in')"
                >
                    Check in
                </Button>

                <template v-else>
                    <Button
                        v-if="dashboard.state === 'away'"
                        class="h-14 text-lg"
                        :disabled="processing"
                        @click="post('/work/back')"
                    >
                        I'm back
                    </Button>
                    <Button
                        v-else
                        variant="outline"
                        class="h-12"
                        :disabled="processing"
                        @click="showBreak = !showBreak"
                    >
                        Take a break
                    </Button>

                    <form
                        v-if="showBreak && dashboard.state === 'working'"
                        class="flex gap-2"
                        @submit.prevent="startBreak"
                    >
                        <Input
                            v-model="breakForm.reason"
                            placeholder="Why? (lunch, prayer...)"
                            maxlength="500"
                            class="h-11"
                        />
                        <Button
                            type="submit"
                            class="h-11"
                            :disabled="
                                breakForm.processing ||
                                breakForm.reason.trim() === ''
                            "
                        >
                            Start
                        </Button>
                    </form>

                    <Button
                        variant="destructive"
                        class="h-12"
                        :disabled="processing"
                        @click="post('/work/check-out')"
                    >
                        Check out
                    </Button>
                </template>
            </div>
        </CardContent>
    </Card>

    <Card v-if="dashboard.reportPrompt">
        <CardHeader class="pb-2">
            <CardTitle class="text-base">
                Your report for {{ dashboard.reportPrompt.label }}
            </CardTitle>
        </CardHeader>
        <CardContent>
            <form class="space-y-3" @submit.prevent="saveReport">
                <div class="space-y-1">
                    <Label for="report">What did you do?</Label>
                    <textarea
                        id="report"
                        v-model="reportForm.report"
                        :class="textarea"
                        maxlength="2000"
                    />
                    <p
                        v-if="reportForm.errors.report"
                        class="text-sm text-destructive"
                    >
                        {{ reportForm.errors.report }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label for="blockers">Any blockers? (optional)</Label>
                    <textarea
                        id="blockers"
                        v-model="reportForm.blockers"
                        :class="textarea"
                        maxlength="2000"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="
                        reportForm.processing || reportForm.report.trim() === ''
                    "
                >
                    Save report
                </Button>
            </form>
        </CardContent>
    </Card>

    <Card v-if="dashboard.correction" class="border-amber-500/50">
        <CardHeader class="pb-2">
            <CardTitle class="text-base">You forgot to check out</CardTitle>
        </CardHeader>
        <CardContent>
            <p class="mb-3 text-sm text-muted-foreground">
                Your session of {{ dashboard.correction.label }} was closed
                automatically at {{ dashboard.correction.endTime }}. If that is
                not right, give your real end time.
            </p>
            <form class="space-y-3" @submit.prevent="saveCorrection">
                <div class="space-y-1">
                    <Label for="ended-at">Real end time (HH:MM)</Label>
                    <Input
                        id="ended-at"
                        v-model="correctionForm.ended_at"
                        type="time"
                        class="h-11"
                    />
                    <p
                        v-if="correctionForm.errors.ended_at"
                        class="text-sm text-destructive"
                    >
                        {{ correctionForm.errors.ended_at }}
                    </p>
                </div>
                <div class="space-y-1">
                    <Label for="note">Note (optional)</Label>
                    <Input
                        id="note"
                        v-model="correctionForm.note"
                        class="h-11"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="
                        correctionForm.processing ||
                        correctionForm.ended_at === ''
                    "
                >
                    Send correction
                </Button>
            </form>
        </CardContent>
    </Card>

    <div class="grid gap-4 sm:grid-cols-2">
        <Card v-for="period in periods" :key="period.key">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">{{ period.title }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Worked</span>
                    <span class="font-medium tabular-nums">
                        {{ period.data.workedFormatted }}
                    </span>
                </div>
                <div class="flex justify-between">
                    <span class="text-muted-foreground">Target so far</span>
                    <span class="font-medium tabular-nums">
                        {{ period.data.expectedFormatted }}
                    </span>
                </div>
                <div class="flex justify-between border-t pt-2">
                    <span class="text-muted-foreground">To make up</span>
                    <span
                        class="font-semibold tabular-nums"
                        :class="
                            period.data.behindBeforeTodayMinutes > 0
                                ? 'text-amber-600'
                                : ''
                        "
                    >
                        {{ period.data.behindBeforeTodayFormatted }}
                    </span>
                </div>
                <p class="text-xs text-muted-foreground">
                    Missing from earlier days.
                    <template v-if="period.data.extraMinutes > 0">
                        You are {{ period.data.extraFormatted }} ahead.
                    </template>
                </p>
            </CardContent>
        </Card>
    </div>

    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="text-base">Recent sessions</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <p
                v-if="dashboard.recent.length === 0"
                class="p-4 text-sm text-muted-foreground"
            >
                Nothing yet. Check in to start.
            </p>
            <ul v-else class="divide-y">
                <li
                    v-for="session in dashboard.recent"
                    :key="session.id"
                    class="space-y-1 px-4 py-3 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium">{{ session.dateLabel }}</span>
                        <span class="text-muted-foreground tabular-nums">
                            {{ session.startLabel }} to
                            {{ session.endLabel ?? 'now' }}
                        </span>
                        <span class="font-medium tabular-nums">
                            {{ session.workedFormatted }}
                        </span>
                    </div>
                    <p v-if="session.autoClosed" class="text-xs text-amber-600">
                        Closed automatically
                    </p>
                    <p
                        v-if="session.report"
                        class="text-xs text-muted-foreground"
                    >
                        {{ session.report }}
                    </p>
                </li>
            </ul>
        </CardContent>
    </Card>
</template>
