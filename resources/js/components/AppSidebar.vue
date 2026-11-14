<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    BookOpen,
    Building2,
    Landmark,
    Store,
    Tags,
    UserRoundCog,
    ClipboardList,
    FileText,
    FolderGit2,
    LayoutGrid,
    Palette,
    Users,
    Settings,
    ShieldCheck,
} from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import OfficeSwitcher from '@/components/kit/OfficeSwitcher.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import { computed, ref, watch } from 'vue';
import type { NavItem } from '@/types';

const page = usePage();

const role = computed(() => page.props.auth.role);

function officeFromUrl(): string | null {
    const query = page.url.split('?')[1];

    return query ? new URLSearchParams(query).get('office') : null;
}

const office = ref<string | null>(officeFromUrl());

watch(
    () => page.url,
    () => {
        office.value = officeFromUrl();
    },
);

const mainNavItems: NavItem[] = [
    {
        title: 'Triage',
        href: '/',
        icon: LayoutGrid,
    },
    {
        title: 'Clients',
        href: '/clients',
        icon: Users,
    },
    {
        title: 'Contracts',
        href: '/contracts',
        icon: FileText,
    },
    {
        title: 'Accounts',
        href: '/accounts',
        icon: Landmark,
        roles: ['owner', 'finance'] as App.Enums.Role[],
    },
    {
        title: 'Employees',
        href: '/employees',
        icon: UserRoundCog,
        roles: ['owner', 'finance'] as App.Enums.Role[],
    },
    {
        title: 'Suppliers',
        href: '/suppliers',
        icon: Store,
        roles: ['owner', 'finance'] as App.Enums.Role[],
    },
    {
        title: 'Categories',
        href: '/categories',
        icon: Tags,
        roles: ['owner', 'finance'] as App.Enums.Role[],
    },
    {
        title: 'Users',
        href: '/users',
        icon: ClipboardList,
        roles: ['owner'] as App.Enums.Role[],
    },
    {
        title: 'Offices',
        href: '/offices',
        icon: Building2,
        roles: ['owner'] as App.Enums.Role[],
    },
    {
        title: 'Settings',
        href: '/settings',
        icon: Settings,
        roles: ['owner'] as App.Enums.Role[],
    },
    {
        title: 'Audit log',
        href: '/audit-log',
        icon: ShieldCheck,
        roles: ['owner'] as App.Enums.Role[],
    },
    ...(import.meta.env.DEV
        ? [
              {
                  title: 'UI kit',
                  href: '/dev/ui',
                  icon: Palette,
                  roles: ['owner'] as App.Enums.Role[],
              },
          ]
        : []),
];

const visibleMainNavItems = computed(() =>
    mainNavItems.filter(
        (item) =>
            !item.roles ||
            (role.value !== null && item.roles.includes(role.value)),
    ),
);

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <div class="px-2 py-2">
                <OfficeSwitcher
                    v-model="office"
                    :offices="page.props.offices"
                />
            </div>
            <NavMain :items="visibleMainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
