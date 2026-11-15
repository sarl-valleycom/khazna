<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BellRing, ChartLine, ShieldCheck } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();

const points = [
    {
        icon: BellRing,
        title: 'Know what needs you today',
        text: 'Every due, by how soon it matters.',
    },
    {
        icon: ChartLine,
        title: 'See the cash ahead',
        text: '30, 60 and 90-day forecasts from real balances.',
    },
    {
        icon: ShieldCheck,
        title: 'Every change on record',
        text: 'Reversals, reasons and a full audit trail.',
    },
];
</script>

<template>
    <div
        class="relative grid min-h-dvh lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]"
    >
        <!-- Brand panel -->
        <aside
            class="relative hidden overflow-hidden bg-sidebar p-12 text-sidebar-foreground lg:flex lg:flex-col"
        >
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(60rem_40rem_at_-10%_-10%,oklch(0.42_0.12_258/0.7),transparent_60%),radial-gradient(40rem_30rem_at_110%_110%,oklch(0.8_0.13_85/0.14),transparent_60%)]"
            />
            <AppLogoIcon
                class="pointer-events-none absolute -right-24 -bottom-24 size-[34rem] text-white opacity-[0.045]"
            />

            <Link :href="home()" class="relative z-10 flex items-center gap-2">
                <AppLogo />
            </Link>

            <div class="relative z-10 my-auto max-w-md">
                <h2
                    class="text-4xl leading-[1.12] font-semibold tracking-tight text-balance text-white"
                >
                    Every dinar, accounted for.
                </h2>
                <p class="mt-4 text-base text-sidebar-foreground/70">
                    One place for what clients owe you, what you owe others, and
                    where the money sits.
                </p>

                <ul class="mt-10 space-y-5">
                    <li
                        v-for="point in points"
                        :key="point.title"
                        class="flex items-start gap-4"
                    >
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl border border-white/10 bg-white/[0.06] text-gold"
                        >
                            <component :is="point.icon" class="size-5" />
                        </span>
                        <span>
                            <span class="block font-medium text-white">{{
                                point.title
                            }}</span>
                            <span
                                class="block text-sm text-sidebar-foreground/65"
                                >{{ point.text }}</span
                            >
                        </span>
                    </li>
                </ul>
            </div>

            <p class="relative z-10 text-xs text-sidebar-foreground/45">
                © {{ new Date().getFullYear() }} {{ name }}
            </p>
        </aside>

        <!-- Form -->
        <main class="flex items-center justify-center bg-background px-6 py-12">
            <div class="w-full max-w-[26rem]">
                <Link
                    :href="home()"
                    class="mb-8 flex items-center gap-2.5 lg:hidden"
                >
                    <span
                        class="grid size-9 place-items-center rounded-xl bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-6" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{
                        name
                    }}</span>
                </Link>

                <div
                    class="rounded-2xl border bg-card p-7 shadow-raised sm:p-8"
                >
                    <div v-if="title || description" class="mb-6 space-y-1.5">
                        <h1 v-if="title" class="text-2xl font-semibold">
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>
        </main>
    </div>
</template>
