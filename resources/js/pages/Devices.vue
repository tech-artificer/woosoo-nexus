<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
// import { Device, Table } from '@/types/models';
import { getDeviceColumns } from '@/components/devices/components/columns';
// import DeviceTable from '@/components/devices/Index.vue';
import Device from '@/components/devices/Index.vue';
import { onMounted, reactive, watch } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: '/devices',
    },
];

const props = defineProps<{
    title?: string;
    description?: string;
    devices: any[];
    unassignedTables: any[];
    registrationCodes: any[];
}>()

const columns = reactive({
    devices: getDeviceColumns(props.unassignedTables),
    // codes: getDeviceRegistrationCodeColumns()
});

// Watch for changes in props.unassignedTables
watch(() => props.unassignedTables, (newVal) => {
    columns.devices = getDeviceColumns(newVal);
});

onMounted(() => {
    console.log('props', props.devices);
});


</script>

<template>
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <Device :rows="devices" :columns="columns.devices" />
    </AppLayout>

</template>
