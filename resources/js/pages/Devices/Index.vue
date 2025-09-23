<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';

import { columns } from '@/components/Devices/columns';
import DataTable from '@/components/Devices/DataTable.vue'
import type { Device } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: route('devices.index'), 
    },
];

defineProps<{
    title: string;
    description: string;
    devices: Device[];
}>()    

</script>

<template>

   
    <AppLayout :breadcrumbs="breadcrumbs">    
        <!-- <pre> {{ devices }} </pre> -->
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
             <DataTable :data="devices" :columns="columns" />
        </div>
    </AppLayout>
</template>
