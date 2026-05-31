<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/core'
import { ref } from 'vue'
// import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import type { Menu } from '@/types/models';
import { columns } from '@/components/Menus/columns';
import DataTable from '@/components/Menus/DataTable.vue';
import StatsCards from '@/components/Stats/StatsCards.vue'
// import { useToast } from '@/composables/useToast';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Menus',
        href: '/menus',
    },
];

defineProps<{
    title?: string;
    description?: string;
    menus: Menu[];
    stats?: any;
}>()

const search = ref('')
const categoryFilter = ref('')
const availabilityFilter = ref('')

// Filters moved to Menus DataTable toolbar; keep page simple and pass raw menus into the DataTable

// categories may be provided on page.props.categories; fallback empty
const page = usePage()
const categoriesFromPage = (page && (page.props as any).categories) ? (page.props as any).categories : []



</script>

<template>
    <Head :title="title" :description="description" />
    
   <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero Section -->
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative space-y-3">
                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Menu management</span>
                    <div>
                        <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Menus</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Manage menu items, update availability, and review item-level detail across all categories.</p>
                    </div>
                </div>
            </div>

            <!-- Stats Section -->
            <StatsCards :cards="(stats ?? [
                { title: 'Total Menus', value: (menus || []).length, subtitle: 'All menu items', variant: 'primary' },
                { title: 'Available', value: (menus || []).filter(m => m.is_available).length, subtitle: 'Currently available', variant: 'accent' },
                { title: 'Unavailable', value: (menus || []).filter(m => !m.is_available).length, subtitle: 'Currently unavailable', variant: 'destructive' },
            ])" />

            <!-- Menu Table Section -->
            <div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="p-4 sm:p-6">
                    <DataTable :data="menus" :columns="columns" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
