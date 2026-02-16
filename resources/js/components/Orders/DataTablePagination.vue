<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { DeviceOrder } from '@/types/models';
import DataTablePagination from '@/components/ui/DataTablePagination.vue'

interface DataTablePaginationProps {
  table: Table<DeviceOrder>
}

defineProps<DataTablePaginationProps>()
</script>

<template>
  <DataTablePagination :table="(table as any)" />
</template>

