<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { updateQuery } from '@/lib/query';

type Option = { value: string; label: string };

type Props = {
    filters: App.Data.TriageFiltersData;
    kinds: Option[];
    directions: Option[];
    clients: App.Data.ContractClientOptionData[];
    offices: App.Data.OfficeOptionData[];
    assignees: App.Data.AccountManagerOptionData[];
};

const props = defineProps<Props>();

function choose(key: string, value: unknown) {
    const text = String(value);

    updateQuery({ [key]: text === 'all' ? null : text, page: null });
}
</script>

<template>
    <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:items-center">
        <Select
            :model-value="props.filters.direction || 'all'"
            @update:model-value="(v) => choose('direction', v)"
        >
            <SelectTrigger class="w-full sm:w-40" aria-label="Direction">
                <SelectValue placeholder="Direction" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">In and out</SelectItem>
                <SelectItem
                    v-for="option in props.directions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            :model-value="props.filters.kind || 'all'"
            @update:model-value="(v) => choose('kind', v)"
        >
            <SelectTrigger class="w-full sm:w-40" aria-label="Kind">
                <SelectValue placeholder="Kind" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All kinds</SelectItem>
                <SelectItem
                    v-for="option in props.kinds"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            :model-value="
                props.filters.client === null
                    ? 'all'
                    : String(props.filters.client)
            "
            @update:model-value="(v) => choose('client', v)"
        >
            <SelectTrigger class="w-full sm:w-48" aria-label="Client">
                <SelectValue placeholder="Client" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All clients</SelectItem>
                <SelectItem
                    v-for="client in props.clients"
                    :key="client.id"
                    :value="String(client.id)"
                >
                    {{ client.name }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            v-if="props.offices.length > 1"
            :model-value="
                props.filters.office === null
                    ? 'all'
                    : String(props.filters.office)
            "
            @update:model-value="(v) => choose('office', v)"
        >
            <SelectTrigger class="w-full sm:w-44" aria-label="Office">
                <SelectValue placeholder="Office" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">All offices</SelectItem>
                <SelectItem
                    v-for="office in props.offices"
                    :key="office.id"
                    :value="String(office.id)"
                >
                    {{ office.name }}
                </SelectItem>
            </SelectContent>
        </Select>

        <Select
            v-if="props.assignees.length > 0"
            :model-value="
                props.filters.assignee === null
                    ? 'all'
                    : String(props.filters.assignee)
            "
            @update:model-value="(v) => choose('assignee', v)"
        >
            <SelectTrigger class="w-full sm:w-48" aria-label="Assignee">
                <SelectValue placeholder="Assignee" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Everyone</SelectItem>
                <SelectItem
                    v-for="assignee in props.assignees"
                    :key="assignee.id"
                    :value="String(assignee.id)"
                >
                    {{ assignee.name }}
                </SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
