<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Mail, MapPin, Phone, UserRound } from '@lucide/vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import EmptyState from '@/components/kit/EmptyState.vue';
import MoneyText from '@/components/kit/MoneyText.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { formatDate } from '@/lib/format';

type Props = {
    client: App.Data.ClientShowData;
    contracts: App.Data.ContractData[];
};

const props = defineProps<Props>();
const page = usePage();
</script>

<template>
    <Head :title="props.client.name" />
    <AppLayout
        :breadcrumbs="[
            { title: 'Clients', href: '/clients' },
            { title: props.client.name, href: `/clients/${props.client.id}` },
        ]"
    >
        <div class="flex flex-1 flex-col gap-4 p-4">
            <PageHeader
                :title="props.client.name"
                :description="`Account manager: ${props.client.accountManagerName}`"
            >
                <template #actions>
                    <Button variant="outline" as-child>
                        <Link href="/clients">
                            <ArrowLeft class="mr-1 size-4" /> All clients
                        </Link>
                    </Button>
                </template>
            </PageHeader>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Contact details</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="flex items-start gap-3">
                            <UserRound
                                class="mt-0.5 size-4 text-muted-foreground"
                            />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Contact person
                                </p>
                                <p>{{ props.client.contact ?? '—' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Phone
                                class="mt-0.5 size-4 text-muted-foreground"
                            />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Phone
                                </p>
                                <p>{{ props.client.phoneDisplay }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <Mail class="mt-0.5 size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Email
                                </p>
                                <p>{{ props.client.email ?? '—' }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <MapPin
                                class="mt-0.5 size-4 text-muted-foreground"
                            />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Address
                                </p>
                                <p>{{ props.client.address ?? '—' }}</p>
                            </div>
                        </div>
                        <div v-if="props.client.notes" class="sm:col-span-2">
                            <p class="text-xs text-muted-foreground">Notes</p>
                            <p class="mt-1 whitespace-pre-line">
                                {{ props.client.notes }}
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Office</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-1 text-sm">
                            <p>
                                {{ props.client.officeName ?? 'All offices' }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ props.client.accountManagerName }} ·
                                {{ props.client.accountManagerEmail }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Total collected</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <MoneyText
                                :cents="0"
                                :symbol="page.props.settings.currencySymbol"
                                direction="in"
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Contracts</CardTitle>
                </CardHeader>
                <CardContent v-if="props.contracts.length > 0" class="p-0">
                    <div class="divide-y">
                        <Link
                            v-for="contract in props.contracts"
                            :key="contract.id"
                            :href="`/contracts/${contract.id}`"
                            class="flex items-center justify-between gap-4 p-4 hover:bg-muted/50"
                        >
                            <div>
                                <p class="font-medium">
                                    {{ contract.cycleLabel }} ·
                                    {{ formatDate(contract.startsOn) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ contract.statusLabel }}
                                    <template v-if="contract.endsOn">
                                        · ends
                                        {{ formatDate(contract.endsOn) }}
                                    </template>
                                </p>
                            </div>
                            <MoneyText
                                :cents="contract.amountCents"
                                :symbol="page.props.settings.currencySymbol"
                                direction="in"
                            />
                        </Link>
                    </div>
                </CardContent>
                <CardContent v-else class="p-0">
                    <EmptyState
                        title="No contracts yet"
                        description="Contracts for this client will appear here."
                    />
                </CardContent>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Open dues</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <EmptyState
                            title="No open dues"
                            description="Unpaid and partially paid dues will appear here."
                        />
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Payment history</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0">
                        <EmptyState
                            title="No payments yet"
                            description="Recorded payments will appear here."
                        />
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
