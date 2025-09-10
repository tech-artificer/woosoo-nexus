<script setup lang="ts">
// import { defineProps } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
// import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Menu } from '@/types/models';
import { getMenuColumns } from '@/pages/menu/menu-columns';
// import MenuTable from '@/pages/menus/MenuTable.vue';
import AppTable from '@/pages/menu/MenuTable.vue';
// import { useToast } from '@/composables/useToast';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Menus',
        href: '/menus',
    },
];

const columns = getMenuColumns();

defineProps<{
    title?: string;
    description?: string;
    menus: Menu[];
}>()



</script>

<template>
    <Head :title="title" :description="description" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="relative min-h-[100vh] flex-1 rounded-xl border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <AppTable :rows="menus" :columns="columns"  />
            </div>
        </div>
    </AppLayout>
</template>
