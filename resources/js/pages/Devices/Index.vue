<script setup lang="ts">
// import { onMounted, onUnmounted, } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head} from '@inertiajs/vue3';
import { columns } from '@/components/Devices/columns';
import DataTable from '@/components/Devices/DataTable.vue'
import { Device } from '@/types/models';

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
    <Head :title="title" :description="description" />
   
    <AppLayout :breadcrumbs="breadcrumbs">    
        <!-- <pre> {{ devices }} </pre> -->
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
             <DataTable :data="devices" :columns="columns" />
        </div>
    </AppLayout>
</template>
