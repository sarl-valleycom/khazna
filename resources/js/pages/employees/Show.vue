<script setup lang="ts">
import { computed, ref, watchEffect } from 'vue';
import {
    Head,
    Link,
    router,
    useForm,
    usePage,
    setLayoutProps,
} from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import EmployeeFormDialog from '@/components/employees/EmployeeFormDialog.vue';
import AdjustmentDialog from '@/components/payroll/AdjustmentDialog.vue';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import MonthPdfDialog from '@/components/kit/MonthPdfDialog.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate, toCalendarDate } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Employees', href: '/employees' }] },
});

type Props = {
    newAttendanceCode?: string | null;
    canManageAttendanceCode?: boolean;
    employee:
        | App.Data.EmployeeWithSalaryData
        | { employee: App.Data.EmployeeData };
    canSeeSalaries: boolean;
    offices: App.Data.OfficeOptionData[];
    statuses: Array<{ value: string; label: string }>;
    accounts: App.Data.PaymentAccountData[];
    adjustmentTypes: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const page = usePage();

const editOpen = ref(false);
const salaryOpen = ref(false);
const advanceOpen = ref(false);
const thisMonth = toCalendarDate(new Date()).slice(0, 7);
const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function makeCode() {
    router.post(
        `/employees/${person.value.id}/attendance-code`,
        {},
        { preserveScroll: true },
    );
}

function removeCode() {
    router.delete(`/employees/${person.value.id}/attendance-code`, {
        preserveScroll: true,
    });
}

const person = computed(() => props.employee.employee);

// The static layout breadcrumb only names the section; add the record being viewed.
watchEffect(() =>
    setLayoutProps({
        breadcrumbs: [
            { title: 'Employees', href: '/employees' },
            { title: person.value.name, href: page.url },
        ],
    }),
);
const withSalary = computed(() =>
    'salaryHistory' in props.employee ? props.employee : null,
);

const salaryForm = useForm({
    amount_cents: null as number | null,
    effective_from: toCalendarDate(new Date()) as string | null,
});

function openSalary() {
    salaryForm.reset();
    salaryForm.clearErrors();
    salaryForm.effective_from = toCalendarDate(new Date());
    salaryOpen.value = true;
}

function saveSalary() {
    salaryForm.post(`/employees/${person.value.id}/salaries`, {
        preserveScroll: true,
        onSuccess: () => (salaryOpen.value = false),
    });
}
</script>

<template>
    <Head :title="person.name" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            :title="person.name"
            :description="`${person.title ?? 'No title'} · ${person.officeName}`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/employees">
                        <ArrowLeft class="mr-1 size-4" /> All employees
                    </Link>
                </Button>
                <MonthPdfDialog
                    v-if="props.canSeeSalaries"
                    :href="`/employees/${person.id}/payslip`"
                    title="Payslip"
                    description="Pay, adjustments and hours for the month you choose."
                    button-label="Payslip PDF"
                />
                <Button
                    v-if="props.canSeeSalaries"
                    variant="outline"
                    @click="advanceOpen = true"
                >
                    Advance
                </Button>
                <Button variant="outline" @click="editOpen = true">Edit</Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent class="grid gap-4 pt-6 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">Status</p>
                    <StatusBadge
                        :status="
                            person.status === 'active' ? 'active' : 'inactive'
                        "
                        :labels="{ active: 'Active', inactive: 'Inactive' }"
                    />
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Phone</p>
                    <p class="font-medium">{{ person.phone ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Hired</p>
                    <p class="font-medium">
                        {{
                            person.hireDate ? formatDate(person.hireDate) : '—'
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-if="props.newAttendanceCode" class="border-primary">
            <CardHeader>
                <CardTitle>New attendance code for {{ person.name }}</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2">
                <p
                    class="text-4xl font-semibold tracking-[0.4em] tabular-nums"
                    data-testid="attendance-code"
                >
                    {{ props.newAttendanceCode }}
                </p>
                <p class="text-sm text-muted-foreground">
                    Give it to {{ person.name }} now. It is not shown again, and
                    an older code stops working. They enter it at
                    <span class="font-medium">/work</span>.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Attendance</CardTitle>
            </CardHeader>
            <CardContent class="grid gap-4 pt-0 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-muted-foreground">Attendance code</p>
                    <p class="font-medium">
                        {{
                            person.hasAttendanceCode
                                ? `Set on ${formatDate(person.attendanceCodeSetAt ?? '')}`
                                : 'No code yet'
                        }}
                    </p>
                    <div
                        v-if="
                            props.canManageAttendanceCode &&
                            person.status === 'active'
                        "
                        class="mt-2 flex gap-2"
                    >
                        <Button size="sm" variant="outline" @click="makeCode">
                            {{
                                person.hasAttendanceCode
                                    ? 'Make a new code'
                                    : 'Make a code'
                            }}
                        </Button>
                        <Button
                            v-if="person.hasAttendanceCode"
                            size="sm"
                            variant="ghost"
                            @click="removeCode"
                        >
                            Remove
                        </Button>
                    </div>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Contract</p>
                    <p class="font-medium">{{ person.contractTypeLabel }}</p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Schedule</p>
                    <p class="font-medium">
                        {{
                            person.scheduleStart && person.scheduleEnd
                                ? `${person.scheduleStart.slice(0, 5)}–${person.scheduleEnd.slice(0, 5)}`
                                : 'Not set'
                        }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        Expected per day
                    </p>
                    <p class="font-medium tabular-nums">
                        {{ person.expectedMinutesPerDay }} min
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">Work days</p>
                    <p class="font-medium">
                        {{
                            person.workDays
                                .map((day) => weekDays[day] ?? day)
                                .join(', ')
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-if="withSalary">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>
                    Salary
                    <span
                        class="ml-2 text-base font-normal text-muted-foreground"
                    >
                        now {{ withSalary.currentSalaryFormatted ?? 'not set' }}
                    </span>
                </CardTitle>
                <Button size="sm" @click="openSalary">Add a raise</Button>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="withSalary.salaryHistory.length > 0"
                    class="divide-y"
                >
                    <div
                        v-for="row in withSalary.salaryHistory"
                        :key="row.id"
                        class="flex items-center justify-between p-4 text-sm"
                    >
                        <span>From {{ formatDate(row.effectiveFrom) }}</span>
                        <span class="font-medium tabular-nums">
                            {{ row.amountFormatted }}
                        </span>
                    </div>
                </div>
                <EmptyState
                    v-else
                    title="No salary yet"
                    description="Add the monthly salary and the date it starts."
                />
            </CardContent>
            <p class="px-6 pb-4 text-xs text-muted-foreground">
                A raise never changes a month that was already closed.
            </p>
        </Card>
    </div>

    <AdjustmentDialog
        v-if="props.canSeeSalaries"
        v-model:open="advanceOpen"
        :employees="[{ id: person.id, name: person.name }]"
        :accounts="props.accounts"
        :types="props.adjustmentTypes"
        :month="thisMonth"
        :employee-id="person.id"
        type="advance"
    />

    <EmployeeFormDialog
        v-model:open="editOpen"
        :offices="props.offices"
        :statuses="props.statuses"
        :can-see-salaries="props.canSeeSalaries"
        :employee="person"
        :hourly-rate-cents="withSalary?.hourlyRateCents ?? null"
    />

    <FormDialog
        v-model:open="salaryOpen"
        title="Add a raise"
        description="The new amount applies from the date you choose. Earlier months keep their old amount."
        :processing="salaryForm.processing"
        :errors="salaryForm.errors"
        @submit="saveSalary"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="raise-amount">Monthly salary</Label>
                <MoneyInput
                    id="raise-amount"
                    v-model="salaryForm.amount_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
            <div class="space-y-2">
                <Label for="raise-from">From</Label>
                <DateInput
                    id="raise-from"
                    v-model="salaryForm.effective_from"
                />
            </div>
        </div>
    </FormDialog>
</template>
