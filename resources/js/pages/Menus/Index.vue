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
// import MenuTable from '@/pages/menus/MenuTable.vue';
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
        <div class="flex h-full flex-1 flex-col gap-6">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-2xl font-semibold text-gray-900">Menu Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage menu items and availability</p>
            </div>

            <!-- Stats Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <StatsCards :cards="(stats ?? [
                    { title: 'Total Menus', value: (menus || []).length, subtitle: 'All menu items', variant: 'primary' },
                    { title: 'Available', value: (menus || []).filter(m => m.is_available).length, subtitle: 'Currently available', variant: 'accent' },
                ])" />
            </div>

            <!-- Menu Table Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <DataTable :data="menus" :columns="columns" />
            </div>
        </div>
    </AppLayout>
</template>
