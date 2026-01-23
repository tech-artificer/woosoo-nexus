<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { Role } from './DataTableRowActions.vue'
import DataTablePagination from '@/components/ui/DataTablePagination.vue'

interface DataTablePaginationProps {
  table: Table<Role>
}

defineProps<DataTablePaginationProps>()
</script>

<template>
  <DataTablePagination :table="(table as any)" />
</template>

