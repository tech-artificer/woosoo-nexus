<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import DeviceForm from '@/components/Devices/DeviceForm.vue';
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Devices', href: route('devices.index') }
];

defineProps<{
  title: string;
  description: string;
  device: any;
  unassignedTables: any[];
}>()
</script>

<template>
  <Head :title="title" :description="description" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
      <h2 class="text-lg font-semibold">Edit Device</h2>
      <DeviceForm :device="device" :unassignedTables="unassignedTables" form-type="edit" />
    </div>
  </AppLayout>
</template>
