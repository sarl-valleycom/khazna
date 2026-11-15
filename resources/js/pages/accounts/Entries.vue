<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Paperclip, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ReverseDialog from '@/components/ledger/ReverseDialog.vue';
import AccountSelect from '@/components/kit/AccountSelect.vue';
import DateInput from '@/components/kit/DateInput.vue';
import EmptyState from '@/components/kit/EmptyState.vue';
import FileInput from '@/components/kit/FileInput.vue';
import FormDialog from '@/components/kit/FormDialog.vue';
import MoneyInput from '@/components/kit/MoneyInput.vue';
import PageHeader from '@/components/kit/PageHeader.vue';
import { formatDate, formatMoney, toCalendarDate } from '@/lib/format';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accounts', href: '/accounts' },
            { title: 'Manual entries', href: '/accounts/entries' },
        ],
    },
});

type Entry = {
    id: number;
    date: string;
    accountName: string;
    officeName: string;
    direction: 'in' | 'out';
    amountCents: number;
    categoryName: string;
    description: string;
    hasReceipt: boolean;
    reversed: boolean;
    reversalReason: string | null;
    createdBy: string | null;
    canReverse: boolean;
};

const props = defineProps<{
    entries: Entry[];
    accounts: App.Data.PaymentAccountData[];
    categories: Array<{ id: number; name: string; kind: string }>;
}>();

const page = usePage();
const open = ref(false);
const reversing = ref<Entry | null>(null);
const reverseOpen = ref(false);

const form = useForm({
    account_id: null as number | null,
    direction: 'out',
    amount_cents: null as number | null,
    entry_date: toCalendarDate(new Date()) as string | null,
    category_id: null as number | null,
    description: '',
    receipt: null as File | null,
});

// Money in is filed under income categories, money out under cost ones.
const categoryChoices = computed(() =>
    props.categories.filter(
        (category) =>
            category.kind === (form.direction === 'in' ? 'income' : 'cost'),
    ),
);

function openCreate() {
    form.reset();
    form.clearErrors();
    form.entry_date = toCalendarDate(new Date());
    open.value = true;
}

function setDirection(value: unknown) {
    form.direction = String(value);
    form.category_id = null;
}

function submit() {
    form.post('/accounts/entries', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}

function ask(entry: Entry) {
    reversing.value = entry;
    reverseOpen.value = true;
}

function money(cents: number): string {
    return formatMoney(cents, page.props.settings.currencySymbol);
}
</script>

<template>
    <Head title="Manual entries" />
    <div class="flex flex-1 flex-col gap-4 p-4">
        <PageHeader
            title="Manual entries"
            description="Money that moved with no due behind it: bank fees, taxes, petty cash, owner draws and injections."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/accounts/transfers">Transfers</Link>
                </Button>
                <Button @click="openCreate">
                    <Plus class="mr-1 size-4" /> New entry
                </Button>
            </template>
        </PageHeader>

        <Card>
            <CardContent v-if="props.entries.length > 0" class="p-0">
                <div class="divide-y">
                    <div
                        v-for="entry in props.entries"
                        :key="entry.id"
                        class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between"
                        :class="entry.reversed && 'opacity-60'"
                    >
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ entry.description }}
                                <span
                                    v-if="entry.reversed"
                                    class="text-xs font-normal"
                                    >(reversed)</span
                                >
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDate(entry.date) }} ·
                                {{ entry.accountName }} ·
                                {{ entry.categoryName }}
                                <template v-if="entry.createdBy"
                                    >· by {{ entry.createdBy }}</template
                                >
                                <template v-if="entry.reversalReason"
                                    >· reversed:
                                    {{ entry.reversalReason }}</template
                                >
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="font-medium tabular-nums"
                                :class="
                                    entry.direction === 'in'
                                        ? 'text-success'
                                        : 'text-danger'
                                "
                            >
                                {{ entry.direction === 'in' ? '+' : '−'
                                }}{{ money(entry.amountCents) }}
                            </span>
                            <Button
                                v-if="entry.hasReceipt"
                                variant="ghost"
                                size="sm"
                                as-child
                            >
                                <a
                                    :href="`/accounts/entries/${entry.id}/receipt`"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <Paperclip class="mr-1 size-4" /> Receipt
                                </a>
                            </Button>
                            <Button
                                v-if="entry.canReverse"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="ask(entry)"
                            >
                                Reverse
                            </Button>
                        </div>
                    </div>
                </div>
            </CardContent>
            <CardContent v-else class="p-0">
                <EmptyState
                    title="No manual entries yet"
                    description="Record bank fees, taxes, petty cash and owner draws here."
                />
            </CardContent>
        </Card>
    </div>

    <FormDialog
        v-model:open="open"
        title="New manual entry"
        :processing="form.processing"
        :errors="form.errors"
        wide
        @submit="submit"
    >
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="space-y-2">
                <Label>Account</Label>
                <AccountSelect
                    v-model="form.account_id"
                    :accounts="props.accounts"
                    :allow-all="false"
                    placeholder="Which account?"
                />
            </div>
            <div class="space-y-2">
                <Label>Direction</Label>
                <Select
                    :model-value="form.direction"
                    @update:model-value="setDirection"
                >
                    <SelectTrigger aria-label="Direction"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="out">Money out</SelectItem>
                        <SelectItem value="in">Money in</SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="space-y-2">
                <Label for="entry-amount">Amount</Label>
                <MoneyInput
                    id="entry-amount"
                    v-model="form.amount_cents"
                    :symbol="page.props.settings.currencySymbol"
                />
            </div>
            <div class="space-y-2">
                <Label for="entry-date">Date</Label>
                <DateInput
                    id="entry-date"
                    v-model="form.entry_date"
                    :max="toCalendarDate(new Date())"
                />
            </div>
            <div class="space-y-2">
                <Label>Category</Label>
                <Select
                    :model-value="
                        form.category_id === null
                            ? undefined
                            : String(form.category_id)
                    "
                    @update:model-value="(v) => (form.category_id = Number(v))"
                >
                    <SelectTrigger aria-label="Category"
                        ><SelectValue placeholder="Choose"
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="category in categoryChoices"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>
        <div class="space-y-2">
            <Label for="entry-description">Description</Label>
            <Input id="entry-description" v-model="form.description" />
        </div>
        <div class="space-y-2">
            <Label>Receipt (optional)</Label>
            <FileInput
                v-model="form.receipt"
                accept="image/jpeg,image/png,image/webp,.pdf"
                hint="JPG, PNG, WebP or PDF"
                @invalid="(message) => form.setError('receipt', message)"
            />
        </div>
    </FormDialog>

    <ReverseDialog
        v-if="reversing"
        v-model:open="reverseOpen"
        :action="`/accounts/entries/${reversing.id}/reverse`"
        title="Reverse entry"
        description="An opposite entry is added. The original stays in the statement."
    />
</template>
