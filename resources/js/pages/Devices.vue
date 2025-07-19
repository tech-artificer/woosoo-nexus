<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
// import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Device, Table } from '@/types/models';
import { getDeviceColumns } from '@/pages/device/device-columns';
import AppTable from '@/components/datatable/AppTable.vue';
import { onMounted } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: '/devices',
    },
];


const props = defineProps<{
    title?: string;
    description?: string;
    devices: Device[];
    unassignedTables: Table[];
}>()

const columns = getDeviceColumns(props.unassignedTables);

onMounted(() => {
    console.log('props', props.devices);
    // console.log('props', props.unassignedTables);
});


</script>

<template>
    <Head :title="title" :description="description" />
   
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        
            <div class="relative min-h-[100vh] flex-1 rounded-xl border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <AppTable :rows="devices" :columns="columns"  />
            </div>
        </div>
    </AppLayout>
</template>
