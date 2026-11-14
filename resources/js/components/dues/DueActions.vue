<script setup lang="ts">
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import CancelDueDialog from '@/components/dues/CancelDueDialog.vue';
import PayDueDialog from '@/components/dues/PayDueDialog.vue';
import PostponeDueDialog from '@/components/dues/PostponeDueDialog.vue';

type Props = {
    due: App.Data.DueRowData;
};

const props = defineProps<Props>();
const payOpen = ref(false);
const postponeOpen = ref(false);
const cancelOpen = ref(false);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <Button
            v-if="props.due.abilities.pay"
            size="sm"
            @click="payOpen = true"
        >
            Pay
        </Button>
        <Button
            v-if="props.due.abilities.postpone"
            variant="outline"
            size="sm"
            @click="postponeOpen = true"
        >
            Postpone
        </Button>
        <Button
            v-if="props.due.abilities.cancel"
            variant="ghost"
            size="sm"
            class="text-destructive"
            @click="cancelOpen = true"
        >
            Cancel
        </Button>
    </div>

    <PayDueDialog v-model:open="payOpen" :due="props.due" />
    <PostponeDueDialog v-model:open="postponeOpen" :due="props.due" />
    <CancelDueDialog v-model:open="cancelOpen" :due="props.due" />
</template>
