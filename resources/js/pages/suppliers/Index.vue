<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import PageHeader from '@/components/kit/PageHeader.vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Suppliers', href: '/suppliers' }] },
});

type Supplier = {
    id: number;
    name: string;
    contact: string | null;
    phone: string | null;
    email: string | null;
    notes: string | null;
    liveContracts: number;
    openDues: number;
};

type Props = {
    suppliers: Supplier[];
    filters: { q: string };
};

const props = defineProps<Props>();

const search = ref(props.filters.q);
const dialogOpen = ref(false);
const editing = ref<Supplier | null>(null);
const confirmOpen = ref(false);
const target = ref<Supplier | null>(null);

const form = useForm({
    name: '',
    contact: '',
    phone: '',
    email: '',
    notes: '',
});

function runSearch() {
    router.get(
        '/suppliers',
        { q: search.value || undefined },
        { preserveState: true, replace: true },
    );
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(supplier: Supplier) {
    editing.value = supplier;
    form.clearErrors();
    form.name = supplier.name;
    form.contact = supplier.contact ?? '';
    form.phone = supplier.phone ?? '';
    form.email = supplier.email ?? '';
    form.notes = supplier.notes ?? '';
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.patch(`/suppliers/${editing.value.id}`, options);
        return;
    }

    form.post('/suppliers', options);
}

function askDelete(supplier: Supplier) {
    target.value = supplier;
    confirmOpen.value = true;
}

function destroy() {
    if (!target.value) {
        return;
    }

    router.delete(`/suppliers/${target.value.id}`, {
        preserveScroll: true,
        onFinish: () => (confirmOpen.value = false),
    });
}
</script>

<template>
    <Head title="Suppliers" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Suppliers"
            description="Who the company pays: hosting, rent, tools, freelancers."
        >
            <template #actions>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New supplier
                </Button>
            </template>
        </PageHeader>

        <form class="max-w-sm" @submit.prevent="runSearch">
            <Input
                v-model="search"
                placeholder="Search suppliers…"
                aria-label="Search suppliers"
            />
        </form>

        <Card>
            <CardContent v-if="props.suppliers.length > 0" class="p-0">
                <div class="divide-y">
                    <div
                        v-for="supplier in props.suppliers"
                        :key="supplier.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">{{ supplier.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="supplier.contact">
                                    {{ supplier.contact }} ·
                                </template>
                                {{ supplier.liveContracts }}
                                {{
                                    supplier.liveContracts === 1
                                        ? 'contract'
                                        : 'contracts'
                                }}
                                · {{ supplier.openDues }} open
                                {{ supplier.openDues === 1 ? 'due' : 'dues' }}
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEdit(supplier)"
                            >
                                Edit
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                :aria-label="`Delete ${supplier.name}`"
                                @click="askDelete(supplier)"
                            >
                                Delete
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No suppliers yet"
                    description="Add the people and companies you pay, then attach recurring costs to them."
                />
            </CardContent>
        </Card>
    </div>

    <FormDialog
        v-model:open="dialogOpen"
        :title="editing ? 'Edit supplier' : 'New supplier'"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="supplier-name">Name</Label>
            <Input id="supplier-name" v-model="form.name" />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label for="supplier-contact">Contact (optional)</Label>
                <Input id="supplier-contact" v-model="form.contact" />
            </div>
            <div class="space-y-2">
                <Label for="supplier-phone">Phone (optional)</Label>
                <Input id="supplier-phone" v-model="form.phone" />
            </div>
        </div>
        <div class="space-y-2">
            <Label for="supplier-email">Email (optional)</Label>
            <Input id="supplier-email" v-model="form.email" type="email" />
        </div>
        <div class="space-y-2">
            <Label for="supplier-notes">Notes (optional)</Label>
            <textarea
                id="supplier-notes"
                v-model="form.notes"
                rows="3"
                class="flex min-h-16 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            />
        </div>
    </FormDialog>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Delete supplier?"
        description="A supplier with open dues or live contracts cannot be deleted. History is kept."
        confirm-label="Delete"
        destructive
        :processing="false"
        @confirm="destroy"
    />
</template>
