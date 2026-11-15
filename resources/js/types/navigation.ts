import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    roles?: App.Enums.Role[];
    /** Needs this ability from the signed-in user (for example `view-salaries`). */
    ability?: string;
    /** Section heading the item sits under in the sidebar. */
    group?: string;
};
