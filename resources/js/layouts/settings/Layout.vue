<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: '/settings/profile',
    },
    {
        title: 'Password',
        href: '/settings/password',
    },
    {
        title: 'Appearance',
        href: '/settings/appearance',
    },
];

const page = usePage();

const currentPath = page.props.ziggy?.location ? new URL(page.props.ziggy.location).pathname : '';
</script>

<template>
    <div class="space-y-6 px-1 py-2 md:py-4">
        <div class="max-w-2xl">
            <Heading title="Settings" description="Manage your profile, security, and appearance preferences." />
        </div>

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] lg:items-start">
            <aside class="w-full">
                <div class="rounded-[26px] border border-black/8 bg-white/76 p-3 shadow-[0_24px_70px_-42px_rgba(37,37,37,0.35)] backdrop-blur-xl dark:border-white/10 dark:bg-white/[0.04]">
                <nav class="flex flex-col gap-1.5">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="[
                            'h-11 w-full justify-start rounded-2xl px-4 text-sm font-medium',
                            currentPath === item.href
                                ? 'bg-[#f6b56d]/18 text-foreground shadow-[0_14px_35px_-26px_rgba(176,128,71,0.6)] hover:bg-[#f6b56d]/22'
                                : 'text-muted-foreground hover:bg-black/4 hover:text-foreground dark:hover:bg-white/[0.05]'
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
                </div>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="flex-1">
                <section class="max-w-2xl space-y-12 rounded-[28px] border border-black/8 bg-white/76 px-5 py-6 shadow-[0_24px_70px_-42px_rgba(37,37,37,0.35)] backdrop-blur-xl dark:border-white/10 dark:bg-white/[0.04] md:px-8 md:py-8">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
