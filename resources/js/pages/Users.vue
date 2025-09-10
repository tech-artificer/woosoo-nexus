<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { User } from '@/types/models';
import { Head } from '@inertiajs/vue3';
import { columns } from '@/components/user/columns';
// import UserTable from '@/components/user/Index.vue';
import DataTable from '@/components/user/DataTable.vue';


const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Users',
        href: '/users',
    },
];

defineProps<{
    title: string;
    description: string;
    users: User[],
}>()    

</script>

<template>
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <DataTable :columns="columns" :data="users"  />
        <!-- <UserTable :rows="users" :columns="columns" /> -->
    </AppLayout>
</template>
