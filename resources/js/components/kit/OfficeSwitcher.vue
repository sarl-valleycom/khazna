<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { updateQuery } from '@/lib/query';

type Office = {
    id: number;
    name: string;
    city: string;
};

type Props = {
    offices: Office[];
    reloadProps?: string[];
};

const props = withDefaults(defineProps<Props>(), {
    reloadProps: () => [],
});

const page = usePage<{ auth: App.Data.AuthData }>();
const office = defineModel<string | null>({ default: null });

const restricted = computed(() => page.props.auth?.user?.officeId != null);

const restrictedOffice = computed(() =>
    props.offices.find((item) => item.id === page.props.auth?.user?.officeId),
);

function choose(value: string) {
    const next = value === 'all' ? null : value;
    office.value = next;
    updateQuery({ office: next }, props.reloadProps);
}
</script>

<template>
    <div v-if="!restricted">
        <Select
            :model-value="office ?? 'all'"
            @update:model-value="(value) => choose(String(value))"
        >
            <SelectTrigger
                class="w-full min-w-44 border-sidebar-border bg-sidebar-accent/50 text-sidebar-foreground shadow-none hover:border-sidebar-ring/50 hover:bg-sidebar-accent dark:bg-sidebar-accent/50 dark:hover:bg-sidebar-accent"
            >
                <SelectValue placeholder="All offices" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All offices</SelectItem>
                <SelectItem
                    v-for="item in offices"
                    :key="item.id"
                    :value="String(item.id)"
                >
                    {{ item.name }} · {{ item.city }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
    <div v-else class="text-sm text-muted-foreground">
        {{ restrictedOffice?.name ?? 'Your office' }}
    </div>
</template>
