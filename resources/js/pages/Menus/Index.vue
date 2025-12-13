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
    <!-- <pre> {{ menus }} </pre> -->
          <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
                         <!-- Filters moved into Menus DataTable toolbar -->
                 <StatsCards :cards="(stats ?? [
                     { title: 'Total Menus', value: (menus || []).length, subtitle: 'All menu items', variant: 'primary' },
                     { title: 'Available', value: (menus || []).filter(m => m.is_available).length, subtitle: 'Currently available', variant: 'accent' },
                 ])" />
                 <DataTable :data="menus" :columns="columns" />
          </div>
    </AppLayout>
</template>
