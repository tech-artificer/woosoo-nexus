<script setup lang="ts">
// import { defineProps } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
// import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import type { Menu } from '@/types/models';
import { columns } from '@/components/Menus/columns';
// import MenuTable from '@/pages/menus/MenuTable.vue';
import DataTable from '@/components/Menus/DataTable.vue';
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
}>()



</script>

<template>
    <Head :title="title" :description="description" />
    
   <AppLayout :breadcrumbs="breadcrumbs">    
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
             <DataTable :data="menus" :columns="columns" />
        </div>
    </AppLayout>
</template>
