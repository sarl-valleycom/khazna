<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import EmployeeFormDialog from '@/components/employees/EmployeeFormDialog.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Employees', href: '/employees' }] },
});

// A user without the salary permission receives plain employees; one with it receives
// wrappers that also carry the salary. The page never has to guess which.
type Row = App.Data.EmployeeData & { salary: string | null };

type Props = {
    employees: Array<App.Data.EmployeeData | App.Data.EmployeeWithSalaryData>;
    canSeeSalaries: boolean;
    offices: App.Data.OfficeOptionData[];
    statuses: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const dialogOpen = ref(false);

const rows = computed<Row[]>(() =>
    props.employees.map((item) =>
        'employee' in item
            ? { ...item.employee, salary: item.currentSalaryFormatted }
            : { ...item, salary: null },
    ),
);
</script>

<template>
    <Head title="Employees" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Employees"
            description="The team. Salaries are only shown to people allowed to see them."
        >
            <template #actions>
                <Button @click="dialogOpen = true">
                    <Plus class="mr-1 size-4" /> New employee
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent v-if="rows.length > 0" class="p-0">
                <div class="divide-y">
                    <Link
                        v-for="employee in rows"
                        :key="employee.id"
                        :href="`/employees/${employee.id}`"
                        class="flex flex-col gap-1 p-4 hover:bg-muted/50 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ employee.name }}
                                <StatusBadge
                                    v-if="employee.status !== 'active'"
                                    status="inactive"
                                    class="ml-2"
                                />
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ employee.title ?? 'No title' }} ·
                                {{ employee.officeName }}
                                <template v-if="employee.hireDate">
                                    · since {{ formatDate(employee.hireDate) }}
                                </template>
                            </p>
                        </div>
                        <p
                            v-if="props.canSeeSalaries"
                            class="text-sm font-medium tabular-nums"
                        >
                            {{ employee.salary ?? 'No salary set' }}
                        </p>
                    </Link>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No employees yet"
                    description="Add the team to start preparing salaries."
                />
            </CardContent>
        </Card>
    </div>

    <EmployeeFormDialog
        v-model:open="dialogOpen"
        :offices="props.offices"
        :statuses="props.statuses"
        :can-see-salaries="props.canSeeSalaries"
    />
</template>
