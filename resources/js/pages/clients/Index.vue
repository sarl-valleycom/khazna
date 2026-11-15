<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2, Upload } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
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
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import DataTable from '@/components/kit/DataTable.vue';
import FilterBar from '@/components/kit/FilterBar.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import ExportButton from '@/components/kit/ExportButton.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { useClearErrorsOnEdit } from '@/composables/useClearErrorsOnEdit';
import type { DataTableColumn, SelectFilter } from '@/components/kit/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Clients', href: '/clients' }] },
});

type Props = {
    clients: App.Data.ClientRowData[];
    total: number;
    page: number;
    perPage: number;
    sort: string;
    direction: 'asc' | 'desc';
    filters: App.Data.ClientFiltersData;
    managers: App.Data.AccountManagerOptionData[];
    offices: App.Data.OfficeOptionData[];
    canAssignManager: boolean;
    canImport: boolean;
};

const props = defineProps<Props>();
const page = usePage();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Client', sortable: true },
    { key: 'phoneDisplay', label: 'Phone' },
    { key: 'accountManagerName', label: 'Account manager' },
    { key: 'officeName', label: 'Office' },
    { key: 'actions', label: '', align: 'right' },
];

const managerFilter = computed<SelectFilter>(() => ({
    key: 'account_manager',
    label: 'Account manager',
    value:
        props.filters.accountManager === null
            ? null
            : String(props.filters.accountManager),
    options: props.managers.map((manager) => ({
        value: String(manager.id),
        label: manager.name,
    })),
}));

const canChooseOffice = computed(
    () =>
        props.canAssignManager &&
        (page.props.auth.user?.officeId ?? null) === null,
);

const dialogOpen = ref(false);
const editing = ref<App.Data.ClientRowData | null>(null);
const form = useForm({
    name: '',
    contact: '',
    phone: '',
    email: '',
    address: '',
    notes: '',
    account_manager_id: null as number | null,
    office_id: null as number | null,
});

useClearErrorsOnEdit(form);

const deleting = ref<App.Data.ClientRowData | null>(null);
const deleteOpen = ref(false);
const deleteProcessing = ref(false);

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.account_manager_id = props.canAssignManager
        ? null
        : (page.props.auth.user?.id ?? null);
    form.office_id = page.props.auth.user?.officeId ?? null;
    dialogOpen.value = true;
}

function openEdit(client: App.Data.ClientRowData) {
    editing.value = client;
    form.clearErrors();
    form.name = client.name;
    form.contact = client.contact ?? '';
    form.phone = client.phoneDisplay;
    form.email = client.email ?? '';
    form.address = client.address ?? '';
    form.notes = client.notes ?? '';
    form.account_manager_id = client.accountManagerId;
    form.office_id = client.officeId;
    dialogOpen.value = true;
}

function chooseManager(value: unknown) {
    form.account_manager_id = Number(value);

    const manager = props.managers.find(
        (option) => option.id === Number(value),
    );

    if (manager?.officeId) {
        form.office_id = manager.officeId;
    }
}

function submit() {
    if (editing.value) {
        form.patch(`/clients/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (dialogOpen.value = false),
        });
        return;
    }

    form.post('/clients', {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    });
}

function confirmDelete() {
    if (!deleting.value) {
        return;
    }

    deleteProcessing.value = true;

    router.delete(`/clients/${deleting.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (deleteOpen.value = false),
        onFinish: () => (deleteProcessing.value = false),
    });
}
</script>

<template>
    <Head title="Clients" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Clients"
            description="Search the client list, keep contacts up to date and import a spreadsheet."
        >
            <template #actions>
                <ExportButton href="/clients/export" />
                <Button v-if="props.canImport" variant="outline" as-child>
                    <Link href="/clients/import">
                        <Upload class="mr-1 size-4" /> Import CSV
                    </Link>
                </Button>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New client
                </Button>
            </template>
        </PageHeader>

        <FilterBar
            :search="props.filters.q"
            search-placeholder="Search by name or phone"
            :select="props.canAssignManager ? managerFilter : undefined"
        />

        <DataTable
            :data="props.clients"
            :columns="columns"
            :total="props.total"
            :page="props.page"
            :per-page="props.perPage"
            :sort="props.sort"
            :direction="props.direction"
            empty-title="No clients yet"
            empty-description="Add a client or import your spreadsheet."
        >
            <template #cell="{ row, column }">
                <template v-if="column.key === 'name'">
                    <Link
                        :href="`/clients/${row.id}`"
                        class="font-medium hover:underline"
                    >
                        {{ row.name }}
                    </Link>
                </template>
                <template v-else-if="column.key === 'officeName'">
                    {{ row.officeName ?? 'All offices' }}
                </template>
                <template v-else-if="column.key === 'actions'">
                    <div class="flex justify-end gap-1">
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="`/clients/${row.id}`">View</Link>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEdit(row as App.Data.ClientRowData)"
                        >
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive"
                            :aria-label="`Delete ${row.name}`"
                            @click="
                                deleting = row as App.Data.ClientRowData;
                                deleteOpen = true;
                            "
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </template>
                <template v-else>{{ row[column.key] }}</template>
            </template>
        </DataTable>
    </div>

    <FormDialog
        v-model:open="dialogOpen"
        :title="editing ? 'Edit client' : 'New client'"
        :processing="form.processing"
        :errors="form.errors"
        :inline-fields="[
            'name',
            'phone',
            'email',
            'account_manager_id',
            'office_id',
        ]"
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="client-name">Name</Label>
                <Input id="client-name" v-model="form.name" />
                <InputError :message="form.errors.name" />
            </div>
            <div class="space-y-2">
                <Label for="client-contact">Contact person</Label>
                <Input id="client-contact" v-model="form.contact" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="client-phone">Phone</Label>
                <Input
                    id="client-phone"
                    v-model="form.phone"
                    inputmode="tel"
                    placeholder="0550 12 34 56"
                />
                <InputError :message="form.errors.phone" />
            </div>
            <div class="space-y-2">
                <Label for="client-email">Email</Label>
                <Input id="client-email" v-model="form.email" type="email" />
                <InputError :message="form.errors.email" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Account manager</Label>
                <Select
                    v-if="props.canAssignManager"
                    :model-value="
                        form.account_manager_id === null
                            ? undefined
                            : String(form.account_manager_id)
                    "
                    @update:model-value="
                        (value) => (form.account_manager_id = Number(value))
                    "
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Choose a manager" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="manager in props.managers"
                            :key="manager.id"
                            :value="String(manager.id)"
                        >
                            {{ manager.name }}
                            <span v-if="!manager.active"> · inactive </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p
                    v-else
                    class="flex h-9 items-center rounded-md border bg-muted/40 px-3 text-sm"
                >
                    {{ page.props.auth.user?.name }}
                </p>
                <InputError :message="form.errors.account_manager_id" />
            </div>
            <div v-if="canChooseOffice" class="space-y-2">
                <Label>Office</Label>
                <Select
                    :model-value="
                        form.office_id === null
                            ? 'none'
                            : String(form.office_id)
                    "
                    @update:model-value="
                        (value) =>
                            (form.office_id =
                                value === 'none' ? null : Number(value))
                    "
                >
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none"> Manager's office </SelectItem>
                        <SelectItem
                            v-for="office in props.offices"
                            :key="office.id"
                            :value="String(office.id)"
                        >
                            {{ office.name }} · {{ office.city }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.office_id" />
            </div>
        </div>
        <div class="space-y-2">
            <Label for="client-address">Address</Label>
            <Input id="client-address" v-model="form.address" />
        </div>
        <div class="space-y-2">
            <Label for="client-notes">Notes</Label>
            <textarea
                id="client-notes"
                v-model="form.notes"
                rows="3"
                class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Delete client"
        :description="`Soft-delete ${deleting?.name ?? 'this client'}? Their history stays in the audit log.`"
        confirm-label="Delete"
        destructive
        :processing="deleteProcessing"
        @confirm="confirmDelete"
    />
</template>
