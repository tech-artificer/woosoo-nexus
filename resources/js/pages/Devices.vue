<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Device, Table } from '@/types/models';
import { getDeviceColumns, getDeviceRegistrationCodeColumns } from '@/pages/device/device-columns';
import AppTable from '@/components/datatable/AppTable.vue';
import { onMounted } from 'vue';
// import RegistrationCodes from './device/RegistrationCodes.vue';

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
    registrationCodes: any[];
}>()

// const columns = getDeviceColumns(props.unassignedTables);
const columns = {
    devices: getDeviceColumns(props.unassignedTables),
    codes: getDeviceRegistrationCodeColumns()
}
onMounted(() => {
    console.log('props', props.devices);
    // console.log('props', props.unassignedTables);
});


</script>

<template>
    <Head :title="title" :description="description" />
   
    <AppLayout :breadcrumbs="breadcrumbs">

        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
              <!-- {{ route().current() }} -->
            <div class="relative min-h-[100vh] flex-1 rounded-xl border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <!-- <Tabs default-value="assigned">
                    <TabsList>
                        <TabsTrigger value="assigned">Assigned</TabsTrigger>
                        <TabsTrigger value="unassigned">Unassigned</TabsTrigger>
                        <TabsTrigger value="codes">Codes</TabsTrigger>
                    </TabsList>
                    <TabsContent value="assigned"> -->
                        <AppTable :rows="devices" :columns="columns.devices"  />
                    <!-- </TabsContent> -->
                    <!-- <TabsContent value="unassigned">
                        <AppTable :rows="unassignedTables" :columns="columns.devices"  />
                    </TabsContent>
                   <TabsContent value="codes"> -->
                        <!-- <AppTable :rows="registrationCodes" :columns="columns.codes"  /> -->
                    <!-- </TabsContent> -->
                <!-- </Tabs>  -->
            </div>
        </div>
    </AppLayout>
</template>
