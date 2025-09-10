<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';
// import MainNav from '@/components/dashboard/MainNav.vue'
// import UserNav from '@/components/dashboard/UserNav.vue';
import NavUser from '@/components/NavUser.vue';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-16 w-full shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4 "
    >
        <div class="flex flex-row justify-between items-center w-full">
            <div class="flex flex-row gap-2 justify-between items-center">
            <SidebarTrigger class="-ml-1" />
            <!-- <UserNav /> -->
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
            </div>
             <NavUser class="hover:text-woosoo-dark-gray w-fit h-auto" /> 
        </div>
    </header>
</template>
