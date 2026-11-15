<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import PageHeader from '@/components/kit/PageHeader.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Settings', href: '/attendance/settings' },
        ],
    },
});

type Props = {
    settings: App.Data.AttendanceSettingsData;
};

const props = defineProps<Props>();

const form = useForm({
    auto_close_time: props.settings.autoCloseTime,
    paid_break_minutes: props.settings.paidBreakMinutes,
    self_correction_enabled: props.settings.selfCorrectionEnabled,
    self_correction_hours: props.settings.selfCorrectionHours,
});

function submit() {
    form.patch('/attendance/settings', { preserveScroll: true });
}
</script>

<template>
    <Head title="Attendance settings" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Attendance settings"
            description="Times are Algiers wall-clock times. Every save is written to the audit log."
        />

        <AttendanceNav />

        <form
            class="max-w-3xl space-y-6 rounded-xl border bg-card p-5"
            @submit.prevent="submit"
        >
            <section class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="auto-close-time">Auto-close time</Label>
                    <Input
                        id="auto-close-time"
                        v-model="form.auto_close_time"
                        type="time"
                    />
                    <p class="text-xs text-muted-foreground">
                        Open sessions are closed at this time and marked for
                        review.
                    </p>
                </div>
            </section>

            <section class="max-w-64 space-y-2">
                <Label for="paid-break">Paid break allowance (minutes)</Label>
                <Input
                    id="paid-break"
                    v-model.number="form.paid_break_minutes"
                    type="number"
                    min="0"
                    max="1440"
                />
                <p class="text-xs text-muted-foreground">
                    Break time up to this much each day still counts as work.
                </p>
            </section>

            <section class="space-y-4">
                <div class="flex items-start gap-3">
                    <Checkbox
                        id="self-correction"
                        v-model="form.self_correction_enabled"
                    />
                    <div>
                        <Label for="self-correction"
                            >Allow self-correction</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            When off, every correction waits for the owner.
                        </p>
                    </div>
                </div>
                <div class="max-w-48 space-y-2">
                    <Label for="self-correction-hours"
                        >Self-correction window (hours)</Label
                    >
                    <Input
                        id="self-correction-hours"
                        v-model.number="form.self_correction_hours"
                        type="number"
                        min="1"
                        max="168"
                    />
                </div>
            </section>

            <p
                v-for="(message, field) in form.errors"
                :key="field"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p>

            <Button type="submit" :disabled="form.processing">
                {{ form.processing ? 'Saving…' : 'Save settings' }}
            </Button>
        </form>
    </div>
</template>
