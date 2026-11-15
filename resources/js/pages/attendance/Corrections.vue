<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AttendanceNav from '@/components/attendance/AttendanceNav.vue';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { formatDateTime } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Attendance', href: '/attendance/sessions' },
            { title: 'Corrections', href: '/attendance/corrections' },
        ],
    },
});

type Props = {
    requests: App.Data.CorrectionRequestData[];
};

const props = defineProps<Props>();

const selected = ref<App.Data.CorrectionRequestData | null>(null);
const action = ref<'approve' | 'reject' | null>(null);
const processing = ref(false);

function ask(
    request: App.Data.CorrectionRequestData,
    decision: 'approve' | 'reject',
) {
    selected.value = request;
    action.value = decision;
}

function decide(reason: string | null) {
    if (!selected.value || !action.value || reason === null) {
        return;
    }

    processing.value = true;

    router.patch(
        `/attendance/corrections/${selected.value.id}/${action.value}`,
        { reason },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = null;
                action.value = null;
            },
            onFinish: () => (processing.value = false),
        },
    );
}

function close() {
    selected.value = null;
    action.value = null;
}
</script>

<template>
    <Head title="Attendance corrections" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Correction requests"
            description="Employees asked to fix an automatically closed session. Approving moves the session end time."
        />

        <AttendanceNav />

        <div class="overflow-hidden rounded-xl border bg-card">
            <EmptyState
                v-if="props.requests.length === 0"
                title="No correction requests"
                description="Requests from employees will appear here."
            />
            <div v-else class="divide-y">
                <div
                    v-for="request in props.requests"
                    :key="request.id"
                    class="flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="min-w-0 space-y-1">
                        <p class="font-medium">
                            {{ request.employeeName }}
                            <span
                                class="text-xs font-normal text-muted-foreground"
                            >
                                · {{ request.officeName }}
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Requested
                            {{ formatDateTime(request.createdAt) }} · proposed
                            end
                            <span class="font-medium text-foreground">
                                {{ formatDateTime(request.proposedEndedAt) }}
                            </span>
                            instead of
                            {{
                                request.endedAt
                                    ? formatDateTime(request.endedAt)
                                    : 'open'
                            }}
                        </p>
                        <p
                            v-if="request.note"
                            class="rounded-md bg-muted/50 p-2 text-sm"
                        >
                            {{ request.note }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="inline-flex h-9 items-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-accent"
                            @click="ask(request, 'reject')"
                        >
                            Reject
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                            @click="ask(request, 'approve')"
                        >
                            Approve
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ConfirmDialog
        :open="selected !== null"
        :title="
            action === 'approve' ? 'Approve correction?' : 'Reject correction?'
        "
        :description="
            selected
                ? `${selected.employeeName}'s session will be ${
                      action === 'approve'
                          ? 'moved to the proposed end time'
                          : 'left unchanged'
                  }.`
                : undefined
        "
        :confirm-label="action === 'approve' ? 'Approve' : 'Reject'"
        reason-required
        reason-label="Why?"
        :destructive="action === 'reject'"
        :processing="processing"
        @update:open="(value) => !value && close()"
        @confirm="decide"
    />
</template>
