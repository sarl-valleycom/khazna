<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import DateInput from '@/components/kit/DateInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import { toCalendarDate } from '@/lib/format';
import { usePage } from '@inertiajs/vue3';

type Props = {
    offices: App.Data.OfficeOptionData[];
    statuses: Array<{ value: string; label: string }>;
    canSeeSalaries: boolean;
    employee?: App.Data.EmployeeData | null;
    hourlyRateCents?: number | null;
};

const props = withDefaults(defineProps<Props>(), { employee: null });
const open = defineModel<boolean>('open', { default: false });
const page = usePage();

const form = useForm({
    name: '',
    phone: '',
    title: '',
    hire_date: null as string | null,
    office_id: null as number | null,
    status: 'active',
    contract_type: 'fixed_monthly',
    hourly_rate: null as number | null,
    expected_minutes_per_day: 480,
    work_days: [0, 1, 2, 3, 4] as number[],
    schedule_start: '',
    schedule_end: '',
    late_grace_minutes: 15,
    salary_cents: null as number | null,
    salary_from: null as string | null,
});

const editing = computed(() => props.employee !== null);

const contractTypes = [
    { value: 'fixed_monthly', label: 'Fixed monthly' },
    { value: 'hourly', label: 'Hourly' },
    { value: 'part_time', label: 'Part-time' },
    { value: 'freelancer', label: 'Freelancer' },
];

const weekDays = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
];

function toggleWorkDay(day: number, checked: boolean) {
    form.work_days = checked
        ? [...form.work_days, day].sort((a, b) => a - b)
        : form.work_days.filter((value) => value !== day);
}

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();

    if (props.employee) {
        form.name = props.employee.name;
        form.phone = props.employee.phone ?? '';
        form.title = props.employee.title ?? '';
        form.hire_date = props.employee.hireDate;
        form.office_id = props.employee.officeId;
        form.status = props.employee.status;
        form.contract_type = props.employee.contractType;
        form.expected_minutes_per_day = props.employee.expectedMinutesPerDay;
        form.work_days = [...props.employee.workDays];
        form.schedule_start = props.employee.scheduleStart?.slice(0, 5) ?? '';
        form.schedule_end = props.employee.scheduleEnd?.slice(0, 5) ?? '';
        form.late_grace_minutes = props.employee.lateGraceMinutes;

        form.hourly_rate = props.hourlyRateCents ?? null;

        return;
    }

    form.office_id =
        page.props.auth.user?.officeId ?? props.offices[0]?.id ?? null;
    form.salary_from = toCalendarDate(new Date());
});

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.employee) {
        // A raise is its own action on the employee page, never part of editing the person.
        form.transform(
            ({ salary_cents: _c, salary_from: _f, hourly_rate, ...rest }) =>
                props.canSeeSalaries ? { ...rest, hourly_rate } : rest,
        ).patch(`/employees/${props.employee.id}`, options);
        return;
    }

    form.transform(({ salary_cents, salary_from, hourly_rate, ...rest }) =>
        props.canSeeSalaries
            ? { ...rest, salary_cents, salary_from, hourly_rate }
            : rest,
    ).post('/employees', options);
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="editing ? 'Edit employee' : 'New employee'"
        :processing="form.processing"
        :errors="form.errors"
        wide
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="employee-name">Name</Label>
                <Input id="employee-name" v-model="form.name" />
            </div>
            <div class="space-y-2">
                <Label for="employee-title">Title (optional)</Label>
                <Input id="employee-title" v-model="form.title" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="employee-phone">Phone (optional)</Label>
                <Input id="employee-phone" v-model="form.phone" />
            </div>
            <div class="space-y-2">
                <Label for="employee-hire">Hire date (optional)</Label>
                <DateInput id="employee-hire" v-model="form.hire_date" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Office</Label>
                <Select
                    :model-value="
                        form.office_id === null
                            ? undefined
                            : String(form.office_id)
                    "
                    @update:model-value="(v) => (form.office_id = Number(v))"
                >
                    <SelectTrigger aria-label="Office">
                        <SelectValue placeholder="Choose an office" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="office in props.offices"
                            :key="office.id"
                            :value="String(office.id)"
                        >
                            {{ office.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div v-if="editing" class="space-y-2">
                <Label>Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger aria-label="Status">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="status in props.statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Contract type</Label>
                <Select v-model="form.contract_type">
                    <SelectTrigger aria-label="Contract type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="type in contractTypes"
                            :key="type.value"
                            :value="type.value"
                        >
                            {{ type.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div v-if="props.canSeeSalaries" class="space-y-2">
                <Label for="employee-hourly-rate">Hourly rate</Label>
                <MoneyInput
                    id="employee-hourly-rate"
                    v-model="form.hourly_rate"
                    :symbol="page.props.settings.currencySymbol"
                />
                <p class="text-xs text-muted-foreground">
                    Only used for hourly and freelancer contracts in payroll.
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="employee-start">Schedule start</Label>
                <Input
                    id="employee-start"
                    v-model="form.schedule_start"
                    type="time"
                />
            </div>
            <div class="space-y-2">
                <Label for="employee-end">Schedule end</Label>
                <Input
                    id="employee-end"
                    v-model="form.schedule_end"
                    type="time"
                />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="employee-expected">Expected minutes per day</Label>
                <Input
                    id="employee-expected"
                    v-model.number="form.expected_minutes_per_day"
                    type="number"
                    min="0"
                    max="1440"
                />
                <p class="text-xs text-muted-foreground">
                    Used for auto-close and the weekly expected hours. 480 means
                    8 hours.
                </p>
            </div>
            <div class="space-y-2">
                <Label for="employee-grace">Late grace (minutes)</Label>
                <Input
                    id="employee-grace"
                    v-model.number="form.late_grace_minutes"
                    type="number"
                    min="0"
                    max="240"
                />
                <p class="text-xs text-muted-foreground">
                    An employee without a schedule is never marked late.
                </p>
            </div>
        </div>

        <div class="space-y-2">
            <Label>Work days</Label>
            <div class="flex flex-wrap gap-4">
                <label
                    v-for="day in weekDays"
                    :key="day.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :model-value="form.work_days.includes(day.value)"
                        @update:model-value="
                            (value) => toggleWorkDay(day.value, value === true)
                        "
                    />
                    {{ day.label }}
                </label>
            </div>
            <p class="text-xs text-muted-foreground">
                Expected time excludes days that are not ticked, holidays and
                approved leave.
            </p>
        </div>

        <div
            v-if="!editing && props.canSeeSalaries"
            class="grid gap-4 sm:grid-cols-2"
        >
            <div class="space-y-2">
                <Label for="employee-salary">Monthly salary</Label>
                <MoneyInput
                    id="employee-salary"
                    v-model="form.salary_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
            <div class="space-y-2">
                <Label for="employee-salary-from">From</Label>
                <DateInput
                    id="employee-salary-from"
                    v-model="form.salary_from"
                />
            </div>
        </div>
    </FormDialog>
</template>
