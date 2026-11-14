<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import AccountFormDialog from '@/components/accounts/AccountFormDialog.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EmptyState from '@/components/kit/EmptyState.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatCard from '@/components/kit/StatCard.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Accounts', href: '/accounts' }] },
});

type Group = {
    officeId: number;
    officeName: string;
    totalCents: number;
    totalFormatted: string;
    accounts: App.Data.AccountRowData[];
};

type Props = {
    groups: Group[];
    grandTotalCents: number;
    grandTotalFormatted: string;
    canCreate: boolean;
    offices: App.Data.OfficeOptionData[];
    types: Array<{ value: string; label: string }>;
};

const props = defineProps<Props>();
const page = usePage();

const dialogOpen = ref(false);
const editing = ref<App.Data.AccountRowData | null>(null);

function openCreate() {
    editing.value = null;
    dialogOpen.value = true;
}

function openEdit(account: App.Data.AccountRowData) {
    editing.value = account;
    dialogOpen.value = true;
}
</script>

<template>
    <Head title="Accounts" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Accounts"
            description="Where the money is. Each balance is the opening balance plus every ledger entry."
        >
            <template #actions>
                <Button v-if="props.canCreate" @click="openCreate">
                    <Plus class="mr-1 size-4" /> New account
                </Button>
            </template>
        </PageHeader>

        <StatCard
            label="Total across active accounts"
            :value="props.grandTotalFormatted"
        />

        <Card v-if="props.groups.length === 0">
            <CardContent class="p-0">
                <EmptyState
                    title="No accounts yet"
                    description="Create the cash boxes, CCP and bank accounts of each office to start recording payments."
                />
            </CardContent>
        </Card>

        <Card v-for="group in props.groups" :key="group.officeId">
            <CardHeader class="flex flex-row items-center justify-between">
                <CardTitle>{{ group.officeName }}</CardTitle>
                <MoneyText
                    :cents="group.totalCents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </CardHeader>
            <CardContent class="p-0">
                <div class="divide-y">
                    <div
                        v-for="account in group.accounts"
                        :key="account.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <Link
                                :href="`/accounts/${account.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ account.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ account.typeLabel }} · opened
                                {{ formatDate(account.openingDate) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <StatusBadge
                                v-if="!account.active"
                                status="inactive"
                            />
                            <MoneyText
                                :cents="account.balanceCents"
                                :symbol="page.props.settings.currencySymbol"
                            />
                            <Button
                                v-if="account.canEdit"
                                variant="outline"
                                size="sm"
                                @click="openEdit(account)"
                            >
                                Edit
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>

    <AccountFormDialog
        v-model:open="dialogOpen"
        :offices="props.offices"
        :types="props.types"
        :account="editing"
    />
</template>
