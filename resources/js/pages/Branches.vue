<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Branch } from '@/types/models';
// import List from '@/components/branches/Index.vue';


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Branches',
        href: route('branches.index'),
    },
];

defineProps<{
    title?: string,
    description?: string,
    branches: Branch[],
}>()



</script>

<template>
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">        
        <!-- <List :rows="branches" /> -->
    </AppLayout>
</template>
