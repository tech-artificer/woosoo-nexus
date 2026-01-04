<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { Menu } from '@/types/models';
import DataTablePagination from '@/components/ui/DataTablePagination.vue'

interface DataTablePaginationProps {
  table: Table<Menu>
}

defineProps<DataTablePaginationProps>()
</script>

<template>
  <DataTablePagination :table="(table as any)" />
</template>

