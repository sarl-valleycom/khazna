<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
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
import MoneyInput from '@/components/kit/MoneyInput.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate, formatMoney } from '@/lib/format';
import { postJson } from '@/lib/http';

type Props = {
    contractId: number;
    milestones: App.Data.MilestoneData[];
    symbol: string;
    canManage: boolean;
};

const props = defineProps<Props>();

type Line = {
    id: number;
    label: string;
    value: number | null;
};

type PlanLine = {
    id: number;
    label: string;
    oldCents: number;
    newCents: number;
    locked: boolean;
    canceled: boolean;
};

const open = ref(false);
const mode = ref<'percent' | 'amount'>('percent');
const lines = ref<Line[]>([]);
const plan = ref<PlanLine[] | null>(null);
const previewError = ref<string | null>(null);
type Payload = { id: number; label: string; mode: string; value: number };
const form = useForm({ milestones: [] as Payload[] });

const unpaid = computed(() =>
    props.milestones.filter((m) => !m.locked && m.dueStatus !== 'canceled'),
);

function deliver(milestone: App.Data.MilestoneData) {
    router.post(
        `/contracts/${props.contractId}/milestones/${milestone.id}/deliver`,
        {},
        { preserveScroll: true },
    );
}

function request(milestone: App.Data.MilestoneData) {
    router.post(
        `/contracts/${props.contractId}/milestones/${milestone.id}/request`,
        {},
        { preserveScroll: true },
    );
}

watch(open, (value) => {
    if (!value) {
        return;
    }

    mode.value = 'amount';
    lines.value = unpaid.value.map((m) => ({
        id: m.id,
        label: m.label,
        value: m.amountCents,
    }));
    plan.value = null;
    previewError.value = null;
    form.clearErrors();
});

watch(mode, () => {
    lines.value = lines.value.map((line) => ({ ...line, value: null }));
    plan.value = null;
});

function payload(): Payload[] {
    return lines.value.map((line) => ({
        id: line.id,
        label: line.label,
        mode: mode.value,
        value: line.value ?? 0,
    }));
}

async function preview() {
    previewError.value = null;

    try {
        const response = await postJson<{ plan: PlanLine[] }>(
            `/contracts/${props.contractId}/milestones/preview`,
            { milestones: payload() },
        );

        plan.value = response.plan;
    } catch (error) {
        plan.value = null;
        previewError.value =
            error instanceof Error ? error.message : 'Could not preview.';
    }
}

function save() {
    form.transform(() => ({ milestones: payload() })).put(
        `/contracts/${props.contractId}/milestones`,
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        },
    );
}
</script>

<template>
    <Card>
        <CardHeader class="flex flex-row items-center justify-between">
            <CardTitle>Milestones</CardTitle>
            <Button
                v-if="props.canManage && unpaid.length > 0"
                variant="outline"
                size="sm"
                @click="open = true"
            >
                Rebalance
            </Button>
        </CardHeader>
        <CardContent class="p-0">
            <div class="divide-y">
                <div
                    v-for="milestone in props.milestones"
                    :key="milestone.id"
                    class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <p class="font-medium">
                            {{ milestone.position }}. {{ milestone.label }}
                            <StatusBadge
                                v-if="milestone.dueStatus"
                                :status="milestone.dueStatus"
                                class="ml-2"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <template v-if="milestone.waitingForDelivery">
                                Waiting for delivery
                            </template>
                            <template v-else-if="milestone.dueOn">
                                Due {{ formatDate(milestone.dueOn) }}
                            </template>
                            <template v-if="milestone.deliveredAt">
                                · delivered
                                {{ formatDate(milestone.deliveredAt) }}
                            </template>
                            <template v-if="milestone.requestedAt">
                                · client asked to pay
                            </template>
                            <template v-if="milestone.paidCents > 0">
                                ·
                                {{
                                    formatMoney(
                                        milestone.paidCents,
                                        props.symbol,
                                    )
                                }}
                                paid
                            </template>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <MoneyText
                            :cents="milestone.amountCents"
                            :symbol="props.symbol"
                            direction="in"
                        />
                        <Button
                            v-if="milestone.canDeliver"
                            size="sm"
                            variant="outline"
                            @click="deliver(milestone)"
                        >
                            Mark delivered
                        </Button>
                        <Button
                            v-if="milestone.canRequest"
                            size="sm"
                            variant="outline"
                            @click="request(milestone)"
                        >
                            Mark as requested
                        </Button>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>

    <FormDialog
        v-model:open="open"
        title="Rebalance milestones"
        description="Share what is left of the contract among the milestones nobody has paid on yet. Paid milestones keep their amount."
        submit-label="Save milestones"
        :processing="form.processing"
        :submit-disabled="plan === null"
        :errors="form.errors"
        wide
        @submit="save"
    >
        <div class="flex items-center gap-2 text-sm">
            <span class="text-muted-foreground">Split by</span>
            <Select v-model="mode">
                <SelectTrigger class="h-8 w-32" aria-label="Split by">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="percent">Percentage</SelectItem>
                    <SelectItem value="amount">Amount</SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div
            v-for="line in lines"
            :key="line.id"
            class="grid gap-2 sm:grid-cols-[1fr_10rem]"
        >
            <div>
                <Label :for="`line-label-${line.id}`" class="sr-only"
                    >Label</Label
                >
                <Input :id="`line-label-${line.id}`" v-model="line.label" />
            </div>
            <Input
                v-if="mode === 'percent'"
                :model-value="line.value ?? undefined"
                @update:model-value="
                    (v) => (line.value = v === '' ? null : Number(v))
                "
                type="number"
                step="0.001"
                :aria-label="`Percentage for ${line.label}`"
            />
            <MoneyInput v-else v-model="line.value" :symbol="props.symbol" />
        </div>

        <div class="flex justify-end">
            <Button type="button" variant="outline" size="sm" @click="preview">
                Preview the change
            </Button>
        </div>

        <p v-if="previewError" class="text-sm text-destructive" role="alert">
            {{ previewError }}
        </p>

        <div v-if="plan" class="rounded-md border" role="status">
            <div
                v-for="item in plan"
                :key="item.id"
                class="flex items-center justify-between gap-3 border-b p-2 text-sm last:border-0"
            >
                <span>
                    {{ item.label }}
                    <span
                        v-if="item.locked"
                        class="text-xs text-muted-foreground"
                    >
                        ({{
                            item.canceled
                                ? 'canceled, not counted'
                                : 'paid, unchanged'
                        }})
                    </span>
                </span>
                <span class="tabular-nums">
                    {{ formatMoney(item.oldCents, props.symbol) }}
                    <template v-if="item.oldCents !== item.newCents">
                        →
                        <strong>{{
                            formatMoney(item.newCents, props.symbol)
                        }}</strong>
                    </template>
                </span>
            </div>
        </div>
    </FormDialog>
</template>
