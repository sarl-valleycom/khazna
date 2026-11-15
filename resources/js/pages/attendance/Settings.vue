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
    digest_time: props.settings.digestTime,
    reminders_enabled: props.settings.remindersEnabled,
    reminder_after_minutes: props.settings.reminderAfterMinutes,
    away_limit_minutes: props.settings.awayLimitMinutes,
    paid_break_minutes: props.settings.paidBreakMinutes,
    self_correction_enabled: props.settings.selfCorrectionEnabled,
    self_correction_hours: props.settings.selfCorrectionHours,
    over_10h_reminder_enabled: props.settings.over10hReminderEnabled,
    owner_digest_channel_id: props.settings.ownerDigestChannelId ?? '',
    reports_channel_id: props.settings.reportsChannelId ?? '',
    offices: props.settings.offices.map((office) => ({
        id: office.id,
        name: office.name,
        attendance_channel_id: office.attendanceChannelId ?? '',
    })),
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
                <div class="space-y-2">
                    <Label for="digest-time">Daily digest time</Label>
                    <Input
                        id="digest-time"
                        v-model="form.digest_time"
                        type="time"
                    />
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-start gap-3">
                    <Checkbox
                        id="reminders-enabled"
                        v-model="form.reminders_enabled"
                    />
                    <div>
                        <Label for="reminders-enabled"
                            >Reminders enabled</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            Sends the not-checked-in and long-break reminders.
                        </p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="reminder-after"
                            >Check-in reminder (minutes)</Label
                        >
                        <Input
                            id="reminder-after"
                            v-model.number="form.reminder_after_minutes"
                            type="number"
                            min="0"
                            max="1440"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="away-limit">Away limit (minutes)</Label>
                        <Input
                            id="away-limit"
                            v-model.number="form.away_limit_minutes"
                            type="number"
                            min="1"
                            max="1440"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="paid-break"
                            >Paid break allowance (minutes)</Label
                        >
                        <Input
                            id="paid-break"
                            v-model.number="form.paid_break_minutes"
                            type="number"
                            min="0"
                            max="1440"
                        />
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <Checkbox
                        id="over-10h"
                        v-model="form.over_10h_reminder_enabled"
                    />
                    <div>
                        <Label for="over-10h"
                            >Remind after 10 hours</Label
                        >
                        <p class="text-xs text-muted-foreground">
                            Asks the employee whether they forgot to check out.
                        </p>
                    </div>
                </div>
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

            <section class="space-y-4">
                <h2 class="text-sm font-semibold">Discord channels</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="owner-digest"
                            >Owner digest channel ID</Label
                        >
                        <Input
                            id="owner-digest"
                            v-model="form.owner_digest_channel_id"
                            inputmode="numeric"
                            placeholder="123456789012345678"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="reports-channel"
                            >Reports channel ID</Label
                        >
                        <Input
                            id="reports-channel"
                            v-model="form.reports_channel_id"
                            inputmode="numeric"
                            placeholder="123456789012345678"
                        />
                    </div>
                </div>

                <div class="space-y-3">
                    <p class="text-xs text-muted-foreground">
                        Each office has its own attendance feed. Leave empty to
                        use the configured fallback.
                    </p>
                    <div
                        v-for="(office, index) in form.offices"
                        :key="office.id"
                        class="grid items-end gap-3 sm:grid-cols-[1fr_2fr]"
                    >
                        <p class="text-sm font-medium">{{ office.name }}</p>
                        <div class="space-y-2">
                            <Label :for="`office-channel-${office.id}`">
                                Channel ID
                            </Label>
                            <Input
                                :id="`office-channel-${office.id}`"
                                v-model="
                                    form.offices[index].attendance_channel_id
                                "
                                inputmode="numeric"
                                placeholder="123456789012345678"
                            />
                        </div>
                    </div>
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
