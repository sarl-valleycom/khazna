<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, FileText, Pencil } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ContractFormDialog from '@/components/contracts/ContractFormDialog.vue';
import ContractStatusActions from '@/components/contracts/ContractStatusActions.vue';
import MilestonesCard from '@/components/contracts/MilestonesCard.vue';
import DueRow from '@/components/dues/DueRow.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import StatusBadge from '@/components/kit/StatusBadge.vue';
import { formatDate, formatDateTime } from '@/lib/format';

defineOptions({
    layout: { breadcrumbs: [{ title: 'Contracts', href: '/contracts' }] },
});

type Props = {
    contract: App.Data.ContractData;
    dues: App.Data.DueRowData[];
    clients: App.Data.ContractClientOptionData[];
    offices: App.Data.OfficeOptionData[];
    cycles: Array<{ value: string; label: string }>;
    canChooseOffice: boolean;
    types: Array<{ value: string; label: string }>;
    suppliers: Array<{ id: number; name: string }>;
    categories: Array<{ id: number; name: string }>;
    milestones: App.Data.MilestoneData[];
};

const props = defineProps<Props>();
const page = usePage();

const dialogOpen = ref(false);
const title = computed(
    () => `${props.contract.typeLabel} · ${props.contract.partyName}`,
);
</script>

<template>
    <Head :title="title" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader :title="title" :description="props.contract.directionLabel">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/contracts">
                        <ArrowLeft class="mr-1 size-4" /> All contracts
                    </Link>
                </Button>
                <Button
                    v-if="props.contract.abilities.update"
                    variant="outline"
                    @click="dialogOpen = true"
                >
                    <Pencil class="mr-1 size-4" /> Edit
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm text-muted-foreground">
                        Amount
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <MoneyText
                        :cents="props.contract.amountCents"
                        :symbol="page.props.settings.currencySymbol"
                        direction="in"
                        class="text-2xl"
                    />
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm text-muted-foreground">
                        Cycle
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ props.contract.cycleLabel }}
                    </p>
                </CardContent>
            </Card>
            <Card>
                <CardHeader>
                    <CardTitle class="text-sm text-muted-foreground">
                        Status
                    </CardTitle>
                </CardHeader>
                <CardContent class="flex flex-wrap items-center gap-3">
                    <StatusBadge
                        :status="props.contract.status"
                        :labels="{
                            [props.contract.status]: props.contract.statusLabel,
                        }"
                    />
                    <ContractStatusActions
                        v-if="props.contract.abilities.changeStatus"
                        :contract-id="props.contract.id"
                        :status="props.contract.status"
                    />
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>Contract details</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                props.contract.clientId ? 'Client' : 'Supplier'
                            }}
                        </p>
                        <Link
                            v-if="props.contract.clientId"
                            :href="`/clients/${props.contract.clientId}`"
                            class="font-medium hover:underline"
                        >
                            {{ props.contract.partyName }}
                        </Link>
                        <p v-else class="font-medium">
                            {{ props.contract.partyName }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Office</p>
                        <p>{{ props.contract.officeName }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            Type and direction
                        </p>
                        <p>
                            {{ props.contract.typeLabel }} ·
                            {{ props.contract.directionLabel }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Period</p>
                        <p>
                            {{ formatDate(props.contract.startsOn) }} –
                            {{
                                props.contract.endsOn
                                    ? formatDate(props.contract.endsOn)
                                    : 'ongoing'
                            }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Auto-renew</p>
                        <p>{{ props.contract.autoRenew ? 'Yes' : 'No' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Created</p>
                        <p>
                            {{ formatDateTime(props.contract.createdAt) }}
                        </p>
                    </div>
                    <div v-if="props.contract.notes" class="sm:col-span-2">
                        <p class="text-xs text-muted-foreground">Notes</p>
                        <p class="mt-1 whitespace-pre-line">
                            {{ props.contract.notes }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Attachment</CardTitle>
                </CardHeader>
                <CardContent>
                    <a
                        v-if="props.contract.attachmentName"
                        :href="`/contracts/${props.contract.id}/attachment`"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center gap-2 text-sm font-medium hover:underline"
                    >
                        <FileText class="size-4" />
                        {{ props.contract.attachmentName }}
                    </a>
                    <p v-else class="text-sm text-muted-foreground">
                        No attachment yet.
                    </p>
                </CardContent>
            </Card>
        </div>

        <MilestonesCard
            v-if="props.milestones.length > 0"
            :contract-id="props.contract.id"
            :milestones="props.milestones"
            :symbol="page.props.settings.currencySymbol"
            :can-manage="props.contract.abilities.update"
        />

        <Card>
            <CardHeader>
                <CardTitle>Dues</CardTitle>
            </CardHeader>
            <CardContent v-if="props.dues.length > 0" class="p-0">
                <div class="divide-y">
                    <DueRow
                        v-for="due in props.dues"
                        :key="due.id"
                        :due="due"
                        :symbol="page.props.settings.currencySymbol"
                    />
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No dues yet"
                    description="Generated and manual dues will appear here."
                />
            </CardContent>
        </Card>
    </div>

    <ContractFormDialog
        v-model:open="dialogOpen"
        :clients="props.clients"
        :offices="props.offices"
        :cycles="props.cycles"
        :can-choose-office="props.canChooseOffice"
        :types="props.types"
        :suppliers="props.suppliers"
        :categories="props.categories"
        :contract="props.contract"
    />
</template>
