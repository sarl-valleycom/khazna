<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Categories', href: '/categories' }] },
});

type Category = {
    id: number;
    name: string;
    kind: string;
    kindLabel: string;
    active: boolean;
    system: boolean;
};

type Props = {
    categories: Category[];
    kinds: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();

const dialogOpen = ref(false);
const editing = ref<Category | null>(null);
const form = useForm({ name: '', kind: 'cost' });

function groupOf(kind: string): Category[] {
    return props.categories.filter((category) => category.kind === kind);
}

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(category: Category) {
    editing.value = category;
    form.clearErrors();
    form.name = category.name;
    form.kind = category.kind;
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.transform(({ name }) => ({ name })).patch(
            `/categories/${editing.value.id}`,
            options,
        );
        return;
    }

    form.post('/categories', options);
}

function toggle(category: Category) {
    router.patch(
        `/categories/${category.id}`,
        { name: category.name, active: !category.active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Categories" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Categories"
            description="How money in and out is filed, for the cash reports. Every recurring cost needs one."
        >
            <template #actions>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New category
                </Button>
            </template>
        </PageHeader>

        <Card v-for="kind in props.kinds" :key="kind.value">
            <CardHeader>
                <CardTitle>{{ kind.label }}s</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y">
                    <div
                        v-for="category in groupOf(kind.value)"
                        :key="category.id"
                        class="flex items-center justify-between gap-3 p-4"
                    >
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ category.name }}</span>
                            <StatusBadge
                                v-if="!category.active"
                                status="inactive"
                            />
                            <span
                                v-if="category.system"
                                class="text-xs text-muted-foreground"
                            >
                                used by the system
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEdit(category)"
                            >
                                Rename
                            </Button>
                            <Button
                                v-if="!category.system"
                                variant="ghost"
                                size="sm"
                                @click="toggle(category)"
                            >
                                {{
                                    category.active ? 'Deactivate' : 'Activate'
                                }}
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <FormDialog
        v-model:open="dialogOpen"
        :title="editing ? 'Rename category' : 'New category'"
        :processing="form.processing"
        :errors="form.errors"
        @submit="submit"
    >
        <div class="space-y-2">
            <Label for="category-name">Name</Label>
            <Input id="category-name" v-model="form.name" />
        </div>
        <div v-if="!editing" class="space-y-2">
            <Label>Kind</Label>
            <Select v-model="form.kind">
                <SelectTrigger aria-label="Kind"><SelectValue /></SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="kind in props.kinds"
                        :key="kind.value"
                        :value="kind.value"
                    >
                        {{ kind.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>
    </FormDialog>
</template>
