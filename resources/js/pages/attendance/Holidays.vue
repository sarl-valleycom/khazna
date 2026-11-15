<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Trash2 } from '@lucide/vue';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Holidays', href: '/attendance/holidays' },
        ],
    },
});

type Props = {
    year: number;
    holidays: App.Data.HolidayData[];
};

const props = defineProps<Props>();

const addOpen = ref(false);
const removing = ref<App.Data.HolidayData | null>(null);
const removeProcessing = ref(false);

const form = useForm({ date: null as string | null, label: '' });

const submitDisabled = computed(
    () => form.date === null || form.label.trim() === '',
);

function openAdd() {
    form.reset();
    form.clearErrors();
    form.date = `${props.year}-01-01`;
    addOpen.value = true;
}

function save() {
    form.post('/attendance/holidays', {
        preserveScroll: true,
        onSuccess: () => (addOpen.value = false),
    });
}

function goToYear(year: number) {
    router.get(
        '/attendance/holidays',
        { year },
        { preserveScroll: true, preserveState: true },
    );
}

function remove(reason: string | null) {
    if (!removing.value || reason === null) {
        return;
    }

    removeProcessing.value = true;

    router.delete(`/attendance/holidays/${removing.value.id}`, {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => (removing.value = null),
        onFinish: () => (removeProcessing.value = false),
    });
}
</script>

<template>
    <Head title="Holidays" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Holidays"
            description="A holiday is not a working day: nobody is expected to work, and no hours are missing. Islamic holidays follow the moon and are announced late, so enter them when they are announced."
        >
            <template #actions>
                <Button @click="openAdd">Add a holiday</Button>
            </template>
        </PageHeader>

        <AttendanceNav />

        <div class="flex items-center gap-2">
            <Button
                variant="outline"
                size="icon-sm"
                aria-label="Previous year"
                @click="goToYear(props.year - 1)"
            >
                <ChevronLeft class="size-4" />
            </Button>
            <span class="min-w-16 text-center text-sm font-medium tabular-nums">
                {{ props.year }}
            </span>
            <Button
                variant="outline"
                size="icon-sm"
                aria-label="Next year"
                @click="goToYear(props.year + 1)"
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <div class="overflow-hidden rounded-xl border bg-card">
            <EmptyState
                v-if="props.holidays.length === 0"
                title="No holidays this year"
                description="Add the public holidays so they are not counted as missing hours."
            />
            <ul v-else class="divide-y">
                <li
                    v-for="holiday in props.holidays"
                    :key="holiday.id"
                    class="flex items-center justify-between gap-3 p-4"
                >
                    <div>
                        <p class="font-medium">{{ holiday.label }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ formatDate(holiday.date) }} ·
                            {{ holiday.weekday }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="text-destructive"
                        :aria-label="`Remove ${holiday.label}`"
                        @click="removing = holiday"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>
        </div>
    </div>

    <FormDialog
        v-model:open="addOpen"
        title="Add a holiday"
        description="Adding a date that was removed before brings it back."
        :processing="form.processing"
        :errors="form.errors"
        :submit-disabled="submitDisabled"
        @submit="save"
    >
        <div class="space-y-4">
            <div class="space-y-2">
                <Label for="holiday-date">Date</Label>
                <DateInput id="holiday-date" v-model="form.date" />
            </div>
            <div class="space-y-2">
                <Label for="holiday-label">Name</Label>
                <Input
                    id="holiday-label"
                    v-model="form.label"
                    maxlength="255"
                    placeholder="e.g. Independence Day"
                />
            </div>
        </div>
    </FormDialog>

    <ConfirmDialog
        :open="removing !== null"
        title="Remove this holiday?"
        :description="
            removing
                ? `${removing.label} on ${formatDate(removing.date)} will count as a working day again.`
                : undefined
        "
        confirm-label="Remove"
        reason-required
        reason-label="Why?"
        destructive
        :processing="removeProcessing"
        @update:open="(value) => !value && (removing = null)"
        @confirm="remove"
    />
</template>
