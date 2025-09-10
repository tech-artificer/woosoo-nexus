<script setup lang="ts">
import { Device } from '@/types/models';
import DataTable from '@/components/devices/components/DataTable.vue'

defineProps<{
    columns: any[], 
    rows: Device[],
}>()  

</script>

<template>
  <div class="hidden h-full flex-1 flex-col md:flex">
    <DataTable :data="rows" :columns="columns" />
  </div>
</template>
