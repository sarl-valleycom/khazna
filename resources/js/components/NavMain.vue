<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

// Sections appear in the order their first item does; ungrouped items share one section.
const sections = computed(() => {
    const bySection = new Map<string, NavItem[]>();

    for (const item of props.items) {
        const key = item.group ?? 'Platform';
        bySection.set(key, [...(bySection.get(key) ?? []), item]);
    }

    return [...bySection.entries()].map(([title, items]) => ({ title, items }));
});
</script>

<template>
    <SidebarGroup
        v-for="section in sections"
        :key="section.title"
        class="px-2 py-1"
    >
        <SidebarGroupLabel
            class="h-6 text-[0.68rem] font-semibold tracking-[0.08em] text-sidebar-foreground/45 uppercase"
        >
            {{ section.title }}
        </SidebarGroupLabel>
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in section.items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
