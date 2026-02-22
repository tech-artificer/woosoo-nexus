<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import type {
  ColumnDef,
  ColumnFiltersState,
  SortingState,
  VisibilityState,
} from '@tanstack/vue-table'
import type { DeviceOrder } from '@/types/models';
import {
  FlexRender,
  getCoreRowModel,
  getFacetedRowModel,
  getFacetedUniqueValues,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,  
  useVueTable,
} from '@tanstack/vue-table'
import { ref, computed, toRef } from 'vue'
import { valueUpdater } from '@/lib/utils'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import DataTablePagination from '@/components/Orders/DataTablePagination.vue'
import DataTableToolbar from '@/components/Orders/DataTableToolbar.vue'

interface DataTableProps {
  columns: ColumnDef<DeviceOrder, any>[]
  data: DeviceOrder[] | any[]
  devices?: any[]
  tables?: any[]
}

const props = defineProps<DataTableProps>()
const emit = defineEmits<{
  (e: 'row-click', order: DeviceOrder): void
}>()

// Use computed to ensure reactivity is properly tracked
const tableData = computed(() => props.data ?? [])
const tableColumns = computed(() => props.columns ?? [])

const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref({})

const table = useVueTable({
  get data() { return tableData.value },
  get columns() { return tableColumns.value },
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
  enableRowSelection: true,
  onSortingChange: updaterOrValue => valueUpdater(updaterOrValue, sorting),
  onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
  onColumnVisibilityChange: updaterOrValue => valueUpdater(updaterOrValue, columnVisibility),
  onRowSelectionChange: updaterOrValue => valueUpdater(updaterOrValue, rowSelection),
  getCoreRowModel: getCoreRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFacetedRowModel: getFacetedRowModel(),
  getFacetedUniqueValues: getFacetedUniqueValues(),
})

const shouldIgnoreRowClick = (event: MouseEvent) => {
  const target = event.target as HTMLElement | null
  if (!target) return false
  return Boolean(
    target.closest('button') ||
    target.closest('a') ||
    target.closest('input') ||
    target.closest('[role="menuitem"]') ||
    target.closest('[data-slot="checkbox"]')
  )
}

const handleRowClick = (event: MouseEvent, order: DeviceOrder) => {
  if (shouldIgnoreRowClick(event)) return
  emit('row-click', order)
}
</script>

<template>
  <div class="space-y-4">
    <DataTableToolbar :table="table" :devices="props.devices" :tables="props.tables" />
    <div class="overflow-hidden rounded-md border bg-background">
      <Table>
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead v-for="header in headerGroup.headers" :key="header.id">
              <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="table.getRowModel().rows?.length">
            <TableRow
              v-for="row in table.getRowModel().rows"
              :key="row.id"
              :data-state="row.getIsSelected() && 'selected'"
              :data-order-id="row.original.id"
              class="cursor-pointer transition-colors hover:bg-muted/50"
              @click="handleRowClick($event, row.original)"
            >
              <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>

          <TableRow v-else>
            <TableCell
              :colspan="columns.length"
              class="h-24 text-center"
            >
              No results.
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <DataTablePagination :table="table" />
  </div>
</template>
