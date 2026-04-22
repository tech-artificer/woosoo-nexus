<script setup lang="ts">
import { computed } from 'vue'
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const currentTitle = computed(() => props.breadcrumbs.at(-1)?.title ?? 'Workspace')
</script>

<template>
    <header class="shrink-0 px-3 pt-3 md:px-5 md:pt-5">
        <div class="flex min-h-[76px] items-center justify-between gap-4 rounded-[26px] border border-white/70 bg-white/82 px-4 py-3 shadow-[0_26px_70px_-42px_rgba(37,37,37,0.45)] backdrop-blur-xl dark:border-white/12 dark:bg-white/[0.07] md:px-5">
            <div class="flex min-w-0 items-center gap-3 md:gap-4">
                <SidebarTrigger class="size-10 rounded-full border border-black/10 bg-white/80 text-foreground shadow-sm hover:bg-white dark:border-white/10 dark:bg-white/[0.05]" />
                <div class="min-w-0">
                    <h2 class="truncate font-header text-lg font-semibold tracking-tight text-foreground md:text-xl">{{ currentTitle }}</h2>
                    <div v-if="breadcrumbs && breadcrumbs.length > 0" class="mt-1 overflow-hidden text-xs text-muted-foreground">
                        <Breadcrumbs :breadcrumbs="breadcrumbs" />
                    </div>
                </div>
            </div>

            <div class="hidden items-center gap-2 rounded-full border border-black/8 bg-[#f6b56d]/14 px-3 py-2 text-xs font-medium text-foreground/80 md:flex dark:border-white/10 dark:bg-white/[0.05]">
                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                Secure admin workspace
            </div>
            </div>
    </header>
</template>
