<script setup lang="ts">
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
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
    salary_cents: null as number | null,
    salary_from: null as string | null,
});

const editing = computed(() => props.employee !== null);

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
            ({ salary_cents: _c, salary_from: _f, ...rest }) => rest,
        ).patch(`/employees/${props.employee.id}`, options);
        return;
    }

    form.transform((data) =>
        props.canSeeSalaries
            ? data
            : { ...data, salary_cents: null, salary_from: null },
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
