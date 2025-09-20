<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { columns } from '@/components/users/columns';
import DataTable from '@/components/users/DataTable.vue'
import type { User } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: route('users.index'),
    },
];

defineProps<{
    title: string;
    description: string;
    users: User[];
}>()    


</script>

<template>
    <Head :title="title" :description="description" />
   
    <AppLayout :breadcrumbs="breadcrumbs">    
        <!-- <pre> {{ users }} </pre> -->
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
             <DataTable :data="users" :columns="columns" />
        </div>
    </AppLayout>
</template>
