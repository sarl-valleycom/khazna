<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdjustmentDialog from '@/components/payroll/AdjustmentDialog.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Payroll', href: '/payroll/close' }] },
});

type Adjustment = {
    id: number;
    employeeId: number;
    employeeName: string;
    type: string;
    typeLabel: string;
    amountCents: number;
    amountFormatted: string;
    reason: string;
    paidOn: string | null;
    accountName: string | null;
};

type Props = {
    month: string;
    monthLabel: string;
    adjustments: Adjustment[];
    employees: Array<{ id: number; name: string }>;
    accounts: App.Data.PaymentAccountData[];
    types: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const open = ref(false);
const month = ref(props.month);

function go() {
    router.get(
        '/payroll/adjustments',
        { month: month.value },
        { preserveState: true, replace: true },
    );
}

function sign(type: string): string {
    return type === 'bonus' ? '+' : '−';
}
</script>

<template>
    <Head title="Payroll adjustments" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Payroll adjustments"
            :description="`Advances, bonuses and deductions that count toward ${props.monthLabel}.`"
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/payroll/close?month=${props.month}`">
                        Month-close
                    </Link>
                </Button>
                <Button @click="open = true">
                    <Plus class="mr-1 size-4" /> New adjustment
                </Button>
            </template>
        </PageHeader>

        <form class="max-w-xs" @submit.prevent="go">
            <Input
                v-model="month"
                type="month"
                aria-label="Month"
                @change="go"
            />
        </form>

        <Card>
            <CardContent v-if="props.adjustments.length > 0" class="p-0">
                <div class="divide-y">
                    <div
                        v-for="adjustment in props.adjustments"
                        :key="adjustment.id"
                        class="flex items-start justify-between gap-3 p-4"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ adjustment.employeeName }} ·
                                {{ adjustment.typeLabel }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ adjustment.reason }}
                                <template v-if="adjustment.paidOn">
                                    · paid
                                    {{ formatDate(adjustment.paidOn) }} from
                                    {{ adjustment.accountName }}
                                </template>
                            </p>
                        </div>
                        <p class="font-medium tabular-nums">
                            {{ sign(adjustment.type)
                            }}{{ adjustment.amountFormatted }}
                        </p>
                    </div>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="Nothing for this month"
                    description="Advances, bonuses and deductions you add show up here."
                />
            </CardContent>
        </Card>
    </div>

    <AdjustmentDialog
        v-model:open="open"
        :employees="props.employees"
        :accounts="props.accounts"
        :types="props.types"
        :month="props.month"
    />
</template>
