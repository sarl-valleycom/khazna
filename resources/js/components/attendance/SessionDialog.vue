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
import FormDialog from '@/components/kit/FormDialog.vue';
import { toDateTimeInput } from '@/lib/format';

type Props = {
    employees: App.Data.AttendanceEmployeeOptionData[];
    session?: App.Data.AttendanceSessionData | null;
};

const props = withDefaults(defineProps<Props>(), { session: null });
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    employee_id: null as number | null,
    started_at: '',
    ended_at: '',
    report: '',
    blockers: '',
    reason: '',
});

const editing = computed(() => props.session !== null);

watch(open, (value) => {
    if (!value) {
        return;
    }

    form.reset();
    form.clearErrors();

    if (props.session) {
        form.employee_id = props.session.employeeId;
        form.started_at = props.session.startedAtLocal;
        form.ended_at = props.session.endedAtLocal ?? '';
        form.report = props.session.report ?? '';
        form.blockers = props.session.blockers ?? '';

        return;
    }

    form.started_at = toDateTimeInput(new Date());
    form.employee_id = props.employees[0]?.id ?? null;
});

const submitDisabled = computed(
    () =>
        form.employee_id === null ||
        form.started_at === '' ||
        form.reason.trim() === '',
);

function submit() {
    const payload = {
        employee_id: form.employee_id,
        started_at: form.started_at,
        ended_at: form.ended_at === '' ? null : form.ended_at,
        report: form.report,
        blockers: form.blockers,
        reason: form.reason,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (props.session) {
        form.transform(() => payload).patch(
            `/attendance/sessions/${props.session.id}`,
            options,
        );
        return;
    }

    form.transform(() => payload).post('/attendance/sessions', options);
}
</script>

<template>
    <FormDialog
        v-model:open="open"
        :title="editing ? 'Edit attendance session' : 'Add attendance session'"
        :description="
            editing
                ? 'Every change needs a reason and is written to the audit log.'
                : 'Record a session nobody checked in. Leave the end empty to keep it open.'
        "
        :processing="form.processing"
        :errors="form.errors"
        :submit-disabled="submitDisabled"
        wide
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Employee</Label>
                <Select
                    :model-value="
                        form.employee_id === null
                            ? undefined
                            : String(form.employee_id)
                    "
                    :disabled="editing"
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
                <Label for="session-started">Started at</Label>
                <Input
                    id="session-started"
                    v-model="form.started_at"
                    type="datetime-local"
                />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="session-ended">Ended at</Label>
            <Input
                id="session-ended"
                v-model="form.ended_at"
                type="datetime-local"
            />
            <p class="text-xs text-muted-foreground">
                Leave empty while the session is still open.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="session-report">Report</Label>
                <textarea
                    id="session-report"
                    v-model="form.report"
                    rows="3"
                    class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="What was done today? (optional)"
                />
            </div>
            <div class="space-y-2">
                <Label for="session-blockers">Blockers</Label>
                <textarea
                    id="session-blockers"
                    v-model="form.blockers"
                    rows="3"
                    class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    placeholder="Anything blocking the work? (optional)"
                />
            </div>
        </div>

        <div class="space-y-2">
            <Label for="session-reason">Reason</Label>
            <textarea
                id="session-reason"
                v-model="form.reason"
                rows="2"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                placeholder="Why is this session being changed? (required)"
            />
        </div>
    </FormDialog>
</template>
