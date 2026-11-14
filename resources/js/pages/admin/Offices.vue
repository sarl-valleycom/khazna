<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import DataTable from '@/components/kit/DataTable.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import type { DataTableColumn } from '@/components/kit/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Offices', href: '/offices' }] },
});

type Props = { offices: App.Data.OfficeRowData[] };

const props = defineProps<Props>();
const columns: DataTableColumn[] = [
    { key: 'name', label: 'Office', sortable: true },
    { key: 'city', label: 'City' },
    { key: 'usersCount', label: 'Users' },
    { key: 'active', label: 'Status' },
    { key: 'actions', label: '', align: 'right' },
];

const dialogOpen = ref(false);
const editing = ref<App.Data.OfficeRowData | null>(null);
const confirmOpen = ref(false);
const target = ref<App.Data.OfficeRowData | null>(null);
const form = useForm({ name: '', city: '', active: true });

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.active = true;
    dialogOpen.value = true;
}

function openEdit(office: App.Data.OfficeRowData) {
    editing.value = office;
    form.clearErrors();
    form.name = office.name;
    form.city = office.city;
    form.active = office.active;
    dialogOpen.value = true;
}

function submit() {
    if (editing.value) {
        form.patch(`/offices/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (dialogOpen.value = false),
        });
        return;
    }

    form.post('/offices', {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    });
}

function askDeactivate(office: App.Data.OfficeRowData) {
    target.value = office;
    confirmOpen.value = true;
}

function deactivate() {
    if (!target.value) {
        return;
    }

    router.patch(
        `/offices/${target.value.id}`,
        { name: target.value.name, city: target.value.city, active: false },
        { preserveScroll: true, onSuccess: () => (confirmOpen.value = false) },
    );
}
</script>

<template>
    <Head title="Offices" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Offices"
            description="The company's locations. At least one active office is required."
        >
            <template #actions>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New office
                </Button>
            </template>
        </PageHeader>

        <DataTable :data="props.offices" :columns="columns">
            <template #cell="{ row, column }">
                <template v-if="column.key === 'active'">
                    <StatusBadge :status="row.active ? 'active' : 'inactive'" />
                </template>
                <template v-else-if="column.key === 'actions'">
                    <div class="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEdit(row as App.Data.OfficeRowData)"
                        >
                            Edit
                        </Button>
                        <Button
                            v-if="row.active"
                            variant="ghost"
                            size="sm"
                            @click="
                                askDeactivate(row as App.Data.OfficeRowData)
                            "
                        >
                            Deactivate
                        </Button>
                    </div>
                </template>
                <template v-else>{{ row[column.key] }}</template>
            </template>
        </DataTable>
    </div>

    <FormDialog
        v-model:open="dialogOpen"
        :title="editing ? 'Edit office' : 'New office'"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="office-name">Name</Label>
            <Input id="office-name" v-model="form.name" />
            <p v-if="form.errors.name" class="text-sm text-destructive">
                {{ form.errors.name }}
            </p>
        </div>
        <div class="space-y-2">
            <Label for="office-city">City</Label>
            <Input id="office-city" v-model="form.city" />
            <p v-if="form.errors.city" class="text-sm text-destructive">
                {{ form.errors.city }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <Checkbox id="office-active" v-model="form.active" />
            <Label for="office-active">Active</Label>
        </div>
    </FormDialog>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Deactivate office?"
        description="The office stays in history but will no longer be offered for new records."
        confirm-label="Deactivate"
        destructive
        :processing="false"
        @confirm="deactivate"
    />
</template>
