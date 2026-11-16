<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, LockKeyhole, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
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
import DataTable from '@/components/kit/DataTable.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import type { DataTableColumn } from '@/components/kit/types';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Users', href: '/users' }] },
});

type Props = {
    users: App.Data.UserRowData[];
    offices: App.Data.OfficeOptionData[];
    roles: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const columns: DataTableColumn[] = [
    { key: 'name', label: 'User', sortable: true },
    { key: 'email', label: 'Email' },
    { key: 'roleLabel', label: 'Role' },
    { key: 'officeName', label: 'Office' },
    { key: 'active', label: 'Status' },
    { key: 'actions', label: '', align: 'right' },
];

const dialogOpen = ref(false);
const editing = ref<App.Data.UserRowData | null>(null);
const form = useForm({
    name: '',
    email: '',
    role: 'account_manager',
    office_id: null as number | null,
    active: true,
    password: '',
    password_confirmation: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.role = 'account_manager';
    form.active = true;
    dialogOpen.value = true;
}

function openEdit(user: App.Data.UserRowData) {
    editing.value = user;
    form.clearErrors();
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.office_id = user.officeId;
    form.active = user.active;
    form.password = '';
    form.password_confirmation = '';
    dialogOpen.value = true;
}

function submit() {
    if (editing.value) {
        form.patch(`/users/${editing.value.id}`, {
            preserveScroll: true,
            onSuccess: () => (dialogOpen.value = false),
        });
        return;
    }

    form.post('/users', {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    });
}

const passwordDialogOpen = ref(false);
const passwordTarget = ref<App.Data.UserRowData | null>(null);
const showPassword = ref(false);
const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

function openSetPassword(user: App.Data.UserRowData) {
    passwordTarget.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    showPassword.value = false;
    passwordDialogOpen.value = true;
}

function generatePassword() {
    const alphabet =
        'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    const bytes = crypto.getRandomValues(new Uint32Array(16));
    const value = Array.from(
        bytes,
        (byte) => alphabet[byte % alphabet.length],
    ).join('');

    passwordForm.password = value;
    passwordForm.password_confirmation = value;
    showPassword.value = true;
}

function submitPassword() {
    if (!passwordTarget.value) {
        return;
    }

    passwordForm.put(`/users/${passwordTarget.value.id}/password`, {
        preserveScroll: true,
        onSuccess: () => (passwordDialogOpen.value = false),
    });
}

function deleteUser(user: App.Data.UserRowData) {
    if (
        !window.confirm(
            `Delete ${user.name} (${user.email})? This cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(`/users/${user.id}`, { preserveScroll: true });
}

function resetPassword(user: App.Data.UserRowData) {
    if (!window.confirm(`Send a password reset link to ${user.email}?`)) {
        return;
    }

    router.post(
        `/users/${user.id}/password-reset`,
        {},
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Users"
            description="Panel accounts are created by the owner. One role per user."
        >
            <template #actions>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New user
                </Button>
            </template>
        </PageHeader>

        <DataTable :data="props.users" :columns="columns">
            <template #cell="{ row, column }">
                <template v-if="column.key === 'active'">
                    <StatusBadge :status="row.active ? 'active' : 'inactive'" />
                </template>
                <template v-else-if="column.key === 'actions'">
                    <div class="flex justify-end gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openEdit(row as App.Data.UserRowData)"
                        >
                            Edit
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :title="`Send password reset link to ${row.email}`"
                            @click="resetPassword(row as App.Data.UserRowData)"
                        >
                            <KeyRound class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            :title="`Set a new password for ${row.name}`"
                            @click="
                                openSetPassword(row as App.Data.UserRowData)
                            "
                        >
                            <LockKeyhole class="size-4" />
                        </Button>
                        <Button
                            v-if="!(row as App.Data.UserRowData).isSelf"
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            :title="`Delete ${row.name}`"
                            @click="deleteUser(row as App.Data.UserRowData)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </template>
                <template v-else-if="column.key === 'officeName'">
                    {{ row.officeName ?? 'All offices' }}
                </template>
                <template v-else>{{ row[column.key] }}</template>
            </template>
        </DataTable>
    </div>

    <FormDialog
        v-model:open="dialogOpen"
        :title="editing ? 'Edit user' : 'New user'"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="user-name">Name</Label>
                <Input id="user-name" v-model="form.name" />
            </div>
            <div class="space-y-2">
                <Label for="user-email">Email</Label>
                <Input id="user-email" v-model="form.email" type="email" />
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Role</Label>
                <Select v-model="form.role">
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="role in props.roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label>Office</Label>
                <Select
                    :model-value="
                        form.office_id === null ? 'all' : String(form.office_id)
                    "
                    @update:model-value="
                        (value) =>
                            (form.office_id =
                                value === 'all' ? null : Number(value))
                    "
                >
                    <SelectTrigger><SelectValue /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All offices</SelectItem>
                        <SelectItem
                            v-for="office in props.offices"
                            :key="office.id"
                            :value="String(office.id)"
                        >
                            {{ office.name }} · {{ office.city }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
        <div v-if="!editing" class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="user-password">Temporary password</Label>
                <Input
                    id="user-password"
                    v-model="form.password"
                    type="password"
                />
            </div>
            <div class="space-y-2">
                <Label for="user-password-confirmation">Confirm</Label>
                <Input
                    id="user-password-confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                />
            </div>
        </div>
        <div class="flex items-center gap-2">
            <Checkbox id="user-active" v-model="form.active" />
            <Label for="user-active">Active</Label>
        </div>
    </FormDialog>

    <FormDialog
        v-model:open="passwordDialogOpen"
        :title="`Set password for ${passwordTarget?.name ?? ''}`"
        description="Passwords are stored hashed and can never be viewed. Set a new one and share it with the user."
        :processing="passwordForm.processing"
        :errors="passwordForm.errors"
        @submit="submitPassword"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="set-password">New password</Label>
                <Input
                    id="set-password"
                    v-model="passwordForm.password"
                    :type="showPassword ? 'text' : 'password'"
                />
            </div>
            <div class="space-y-2">
                <Label for="set-password-confirmation">Confirm</Label>
                <Input
                    id="set-password-confirmation"
                    v-model="passwordForm.password_confirmation"
                    :type="showPassword ? 'text' : 'password'"
                />
            </div>
        </div>
        <Button
            type="button"
            variant="outline"
            size="sm"
            @click="generatePassword"
        >
            Generate a strong password
        </Button>
    </FormDialog>
</template>
