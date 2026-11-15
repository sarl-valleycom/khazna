<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Check, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import ConfirmDialog from '@/components/kit/ConfirmDialog.vue';

const props = defineProps<{ session: App.Data.AttendanceSessionData }>();
const emit = defineEmits<{ edit: [] }>();

const approveOpen = ref(false);
const deleteOpen = ref(false);
const approving = ref(false);
const deleting = ref(false);

function approve(reason: string | null) {
    if (reason === null) {
        return;
    }

    approving.value = true;

    router.post(
        `/attendance/sessions/${props.session.id}/approve`,
        { reason },
        {
            preserveScroll: true,
            onSuccess: () => (approveOpen.value = false),
            onFinish: () => (approving.value = false),
        },
    );
}

function remove(reason: string | null) {
    if (reason === null) {
        return;
    }

    deleting.value = true;

    router.delete(`/attendance/sessions/${props.session.id}`, {
        data: { reason },
        preserveScroll: true,
        onSuccess: () => (deleteOpen.value = false),
        onFinish: () => (deleting.value = false),
    });
}
</script>

<template>
    <div class="flex justify-end gap-1">
        <Button
            v-if="props.session.reviewStatus === 'needs_review'"
            variant="outline"
            size="sm"
            @click="approveOpen = true"
        >
            <Check class="mr-1 size-3.5" /> Approve
        </Button>
        <Button variant="ghost" size="sm" @click="emit('edit')">Edit</Button>
        <Button
            variant="ghost"
            size="icon-sm"
            class="text-destructive"
            :aria-label="`Delete session for ${props.session.employeeName}`"
            @click="deleteOpen = true"
        >
            <Trash2 class="size-4" />
        </Button>
    </div>

    <ConfirmDialog
        v-model:open="approveOpen"
        title="Approve this session?"
        :description="`The session for ${props.session.employeeName} on ${props.session.startedAtLocal.replace('T', ' ')} will be marked as checked.`"
        confirm-label="Approve"
        reason-required
        reason-label="What did you check?"
        :processing="approving"
        @confirm="approve"
    />

    <ConfirmDialog
        v-model:open="deleteOpen"
        title="Delete this session?"
        :description="`The session for ${props.session.employeeName} will be removed from attendance. The audit log keeps the trace.`"
        confirm-label="Delete session"
        reason-required
        reason-label="Why is this session being deleted?"
        destructive
        :processing="deleting"
        @confirm="remove"
    />
</template>
