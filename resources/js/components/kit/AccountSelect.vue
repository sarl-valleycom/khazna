<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type Account = {
    id: number;
    officeId: number;
    officeName: string;
    name: string;
    type: string;
    balanceCents: number;
    balanceFormatted: string;
};

type Props = {
    accounts: Account[];
    placeholder?: string;
};

const props = withDefaults(defineProps<Props>(), {
    placeholder: 'Choose an account',
});

const page = usePage<{ auth: App.Data.AuthData }>();
const model = defineModel<number | null>({ default: null });

const visibleAccounts = computed(() => {
    const officeId = page.props.auth?.user?.officeId;

    if (officeId == null) {
        return props.accounts;
    }

    return props.accounts.filter((account) => account.officeId === officeId);
});

const groups = computed(() => {
    const grouped = new Map<
        number,
        { officeName: string; accounts: Account[] }
    >();

    for (const account of visibleAccounts.value) {
        const group = grouped.get(account.officeId) ?? {
            officeName: account.officeName,
            accounts: [],
        };

        group.accounts.push(account);
        grouped.set(account.officeId, group);
    }

    return [...grouped.values()];
});
</script>

<template>
    <Select
        :model-value="model === null ? 'all' : String(model)"
        @update:model-value="
            (value) => (model = String(value) === 'all' ? null : Number(value))
        "
    >
        <SelectTrigger class="w-full">
            <SelectValue :placeholder="placeholder" />
        </SelectTrigger>
        <SelectContent>
            <SelectItem value="all">All accounts</SelectItem>
            <SelectGroup v-for="group in groups" :key="group.officeName">
                <SelectLabel>{{ group.officeName }}</SelectLabel>
                <SelectItem
                    v-for="account in group.accounts"
                    :key="account.id"
                    :value="String(account.id)"
                >
                    <span
                        class="flex w-full items-center justify-between gap-4"
                    >
                        <span>{{ account.name }}</span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                        >
                            {{ account.balanceFormatted }}
                        </span>
                    </span>
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
