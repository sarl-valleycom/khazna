<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Download, Upload } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import FileInput from '@/components/kit/FileInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Clients', href: '/clients' },
            { title: 'Import', href: '/clients/import' },
        ],
    },
});

type Props = {
    preview: App.Data.ClientImportPreviewData | null;
};

const props = defineProps<Props>();

const form = useForm<{ file: File | null }>({ file: null });
const fileError = ref<string | null>(null);
const confirming = ref(false);

function upload() {
    fileError.value = null;

    form.post('/clients/import/preview', {
        forceFormData: true,
        preserveScroll: true,
    });
}

function confirmImport() {
    if (!props.preview || props.preview.validCount === 0) {
        return;
    }

    confirming.value = true;

    router.post(
        '/clients/import/confirm',
        { token: props.preview.token },
        {
            preserveScroll: true,
            onFinish: () => (confirming.value = false),
        },
    );
}

const statusLabels: Record<string, string> = {
    active: 'Ready',
    warning: 'Duplicate',
    failed: 'Invalid',
};
</script>

<template>
    <Head title="Import clients" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Import clients"
            description="Upload a CSV, check every row, then confirm the import."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/clients">
                        <ArrowLeft class="mr-1 size-4" /> All clients
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <template v-if="!props.preview">
            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>1. Choose a file</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <FileInput
                            v-model="form.file"
                            accept=".csv,text/csv"
                            :max-size-mb="5"
                            @invalid="(message) => (fileError = message)"
                        />

                        <p
                            v-if="fileError || form.errors.file"
                            class="text-sm text-destructive"
                        >
                            {{ fileError ?? form.errors.file }}
                        </p>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                :disabled="!form.file || form.processing"
                                @click="upload"
                            >
                                <Upload class="mr-1 size-4" />
                                {{
                                    form.processing
                                        ? 'Checking…'
                                        : 'Preview import'
                                }}
                            </Button>
                            <Button variant="outline" as-child>
                                <a href="/clients/import/template">
                                    <Download class="mr-1 size-4" />
                                    Download CSV template
                                </a>
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>File rules</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <p class="flex gap-2">
                            <Check class="mt-0.5 size-4 text-emerald-600" />
                            UTF-8 encoding, up to 5 MB.
                        </p>
                        <p class="flex gap-2">
                            <Check class="mt-0.5 size-4 text-emerald-600" />
                            Required columns: name, phone,
                            account_manager_email.
                        </p>
                        <p class="flex gap-2">
                            <Check class="mt-0.5 size-4 text-emerald-600" />
                            Account managers are matched by email.
                        </p>
                        <p class="flex gap-2">
                            <Check class="mt-0.5 size-4 text-emerald-600" />
                            Existing phone numbers are flagged and skipped.
                        </p>
                        <p class="text-xs text-muted-foreground">
                            In Excel, use Save As → CSV UTF-8 (Comma delimited).
                        </p>
                    </CardContent>
                </Card>
            </div>
        </template>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard
                    label="Ready to import"
                    :value="props.preview.validCount"
                />
                <StatCard
                    label="Flagged as duplicate"
                    :value="props.preview.duplicateCount"
                />
                <StatCard
                    label="Invalid rows"
                    :value="props.preview.invalidCount"
                />
            </div>

            <Card>
                <CardHeader
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle>2. Review rows</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ props.preview.fileName }} ·
                            {{ props.preview.totalCount }} rows
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/clients/import">
                                Choose another file
                            </Link>
                        </Button>
                        <Button
                            :disabled="
                                props.preview.validCount === 0 || confirming
                            "
                            @click="confirmImport"
                        >
                            <Check class="mr-1 size-4" />
                            {{
                                confirming
                                    ? 'Importing…'
                                    : `Import ${props.preview.validCount} rows`
                            }}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-16">Line</TableHead>
                                    <TableHead class="w-28">Status</TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Phone</TableHead>
                                    <TableHead>Manager</TableHead>
                                    <TableHead>Details</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="row in props.preview.rows"
                                    :key="row.line"
                                >
                                    <TableCell class="tabular-nums">
                                        {{ row.line }}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            :status="
                                                row.status === 'valid'
                                                    ? 'active'
                                                    : row.status === 'duplicate'
                                                      ? 'warning'
                                                      : 'failed'
                                            "
                                            :labels="statusLabels"
                                        />
                                    </TableCell>
                                    <TableCell>
                                        {{ row.name ?? '—' }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.phoneDisplay ?? '—' }}
                                    </TableCell>
                                    <TableCell>
                                        {{ row.managerEmail ?? '—' }}
                                    </TableCell>
                                    <TableCell class="text-muted-foreground">
                                        {{ row.reason ?? 'Ready to import' }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <p class="text-sm text-muted-foreground">
                Rows marked Invalid or Duplicate are not imported. The preview
                expires after 30 minutes.
            </p>
        </template>
    </div>
</template>
