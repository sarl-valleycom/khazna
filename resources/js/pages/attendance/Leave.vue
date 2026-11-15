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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Leave', href: '/attendance/leave' },
        ],
    },
});

type Props = {
    year: number;
    leaves: App.Data.LeaveEntryData[];
    employees: App.Data.AttendanceEmployeeOptionData[];
    types: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();

const addOpen = ref(false);
const cancelling = ref<App.Data.LeaveEntryData | null>(null);
const cancelProcessing = ref(false);

const form = useForm({
    employee_id: null as number | null,
    type: 'paid',
    from: null as string | null,
    to: null as string | null,
    reason: '',
});

const submitDisabled = computed(
    () =>
        form.employee_id === null ||
        form.from === null ||
        form.to === null ||
        form.to < form.from,
);

function openAdd() {
    form.reset();
    form.clearErrors();
    form.employee_id = props.employees[0]?.id ?? null;
    addOpen.value = true;
}

function save() {
    form.post('/attendance/leave', {
        preserveScroll: true,
        onSuccess: () => (addOpen.value = false),
    });
}

function goToYear(year: number) {
    router.get(
        '/attendance/leave',
        { year },
        { preserveScroll: true, preserveState: true },
    );
}

function cancel(reason: string | null) {
    if (!cancelling.value || reason === null) {
        return;
    }

    cancelProcessing.value = true;

    router.delete(`/attendance/leave/${cancelling.value.id}`, {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => (cancelling.value = null),
        onFinish: () => (cancelProcessing.value = false),
    });
}

function period(leave: App.Data.LeaveEntryData): string {
    return leave.from === leave.to
        ? formatDate(leave.from)
        : `${formatDate(leave.from)} to ${formatDate(leave.to)}`;
}
</script>

<template>
    <Head title="Leave" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Leave"
            description="Leave you record is approved at once. Those days are not expected, so no hours are missing. The type is only a label: it never changes pay, you decide any deduction in the month's adjustments."
        >
            <template #actions>
                <Button @click="openAdd">Add leave</Button>
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
                v-if="props.leaves.length === 0"
                title="No leave this year"
                description="Add leave when someone is away, so their days are not counted as missing hours."
            />
            <ul v-else class="divide-y">
                <li
                    v-for="leave in props.leaves"
                    :key="leave.id"
                    class="flex items-center justify-between gap-3 p-4"
                >
                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ leave.employeeName }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                · {{ leave.officeName }}
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ leave.typeLabel }} · {{ period(leave) }} ·
                            {{ leave.workDays }}
                            {{
                                leave.workDays === 1 ? 'work day' : 'work days'
                            }}
                        </p>
                        <p v-if="leave.reason" class="text-sm">
                            {{ leave.reason }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        class="text-destructive"
                        :aria-label="`Cancel leave for ${leave.employeeName}`"
                        @click="cancelling = leave"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </li>
            </ul>
        </div>
    </div>

    <FormDialog
        v-model:open="addOpen"
        title="Add leave"
        :processing="form.processing"
        :errors="form.errors"
        :submit-disabled="submitDisabled"
        @submit="save"
    >
        <div class="space-y-4">
            <div class="space-y-2">
                <Label>Employee</Label>
                <Select
                    :model-value="
                        form.employee_id === null
                            ? undefined
                            : String(form.employee_id)
                    "
                    @update:model-value="
                        (value) => (form.employee_id = Number(value))
                    "
                >
                    <SelectTrigger aria-label="Employee">
                        <SelectValue placeholder="Choose an employee" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="employee in props.employees"
                            :key="employee.id"
                            :value="String(employee.id)"
                        >
                            {{ employee.name }} · {{ employee.officeName }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label>Type</Label>
                <Select v-model="form.type">
                    <SelectTrigger aria-label="Type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in props.types"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="leave-from">First day</Label>
                    <DateInput id="leave-from" v-model="form.from" />
                </div>
                <div class="space-y-2">
                    <Label for="leave-to">Last day</Label>
                    <DateInput id="leave-to" v-model="form.to" />
                </div>
            </div>
            <div class="space-y-2">
                <Label for="leave-reason">Reason (optional)</Label>
                <Input
                    id="leave-reason"
                    v-model="form.reason"
                    maxlength="1000"
                />
            </div>
        </div>
    </FormDialog>

    <ConfirmDialog
        :open="cancelling !== null"
        title="Cancel this leave?"
        :description="
            cancelling
                ? `${cancelling.employeeName}'s days will count as work days again.`
                : undefined
        "
        confirm-label="Cancel leave"
        reason-required
        reason-label="Why?"
        destructive
        :processing="cancelProcessing"
        @update:open="(value) => !value && (cancelling = null)"
        @confirm="cancel"
    />
</template>
