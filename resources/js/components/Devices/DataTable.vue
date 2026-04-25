<script setup lang="ts">
import type {
  ColumnDef,
  ColumnFiltersState,
  SortingState,
  VisibilityState,
} from '@tanstack/vue-table'
import type { Device } from '@/types/models';
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
import { ref } from 'vue'
import { valueUpdater } from '@/lib/utils'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import DataTablePagination from '@/components/Devices/DataTablePagination.vue'
import DataTableToolbar from '@/components/ui/DataTableToolbar.vue'

interface DataTableProps {
  columns: ColumnDef<Device, any>[]
  data: Device[] | any[]
  emptyActionHref?: string
  emptyActionLabel?: string
}
const props = defineProps<DataTableProps>()
const emit = defineEmits<{
  (e: 'row-click', device: Device): void
}>()

const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref({})

const table = useVueTable({
  get data() { return props.data },
  get columns() { return props.columns },
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

const handleRowClick = (event: MouseEvent, device: Device) => {
  if (shouldIgnoreRowClick(event)) return
  emit('row-click', device)
}
</script>

<template>
  <div class="space-y-4">
    <DataTableToolbar :table="table" />
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
              class="py-10 text-center"
            >
              <div class="mx-auto flex max-w-md flex-col items-center gap-4 rounded-2xl border border-dashed border-border/70 bg-muted/30 px-6 py-8 text-center">
                <div class="space-y-2">
                  <p class="text-base font-medium text-foreground">No devices yet.</p>
                  <p class="text-sm leading-6 text-muted-foreground">
                    Create the first device to generate a security code and make it available for tablet registration.
                  </p>
                </div>

                <Button v-if="emptyActionHref && emptyActionLabel" as-child>
                  <Link :href="emptyActionHref">
                    {{ emptyActionLabel }}
                  </Link>
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>

    <DataTablePagination :table="table" />
  </div>
</template>
