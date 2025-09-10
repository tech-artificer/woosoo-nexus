<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Table } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tables',
        href: '/tables',
    },
];

defineProps<{
    title?: string;
    description?: string;
    tables: Table[];
}>()


</script>

<template>
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
   
        </div>
    </AppLayout>
</template>
