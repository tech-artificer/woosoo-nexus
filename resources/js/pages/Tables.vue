<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Table } from '@/types/models';
import { getTableColumns } from '@/pages/table/table-columns';
import { TableStatus } from '@/types/enums';
import AppTable from '@/components/datatable/AppTable.vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Tables',
        href: '/tables',
    },
];

const columns = getTableColumns();

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
             <AppTable :rows="tables" :columns="columns"  />
        </div>
    </AppLayout>
</template>
