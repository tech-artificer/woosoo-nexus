<script setup lang="ts" generic="TData, TValue">
import { ref, defineProps } from 'vue'
import { ColumnDef, ColumnFiltersState,
  ExpandedState,
  SortingState,
  VisibilityState, } from '@tanstack/vue-table'
import OrderTablePagination from '@/pages/orders/OrderTablePagination.vue'
import { Order } from '@/types/models'

import {
  FlexRender,
  getCoreRowModel,
  useVueTable,
  getPaginationRowModel,
  getFilteredRowModel,
} from '@tanstack/vue-table'

import { Search } from 'lucide-vue-next'
import { Input } from '@/components/ui/input'

import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import { valueUpdater } from '@/lib/utils'

const props = defineProps<{
  columns: ColumnDef<Order, any>[]
  orders: Order[];
}>()

const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref({})
const expanded = ref<ExpandedState>({})

const table = useVueTable({
  get data() { return props.orders },
  get columns() { return props.columns },
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
  getFilteredRowModel: getFilteredRowModel(),
  state: {
      get columnFilters() { return columnFilters.value },
    },
})
</script>

<template>

  <div class="flex flex-col gap-4">

    <div class="relative w-full max-w-sm items-center">
       <Input class="pl-10" placeholder="Filter ..."
                :model-value="table.getColumn('id')?.getFilterValue() as string"
                @update:model-value=" table.getColumn('id')?.setFilterValue($event)" />
      <span class="absolute start-0 inset-y-0 flex items-center justify-center px-2">
        <Search class="size-6 text-muted-foreground" />
      </span>
    </div>

    <div class="p-4 rounded-md">
      <Table>
        <TableHeader>
          <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
            <TableHead v-for="header in headerGroup.headers" :key="header.id">
              <FlexRender
                v-if="!header.isPlaceholder" :render="header.column.columnDef.header"
                :props="header.getContext()"
              />
            </TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <template v-if="table.getRowModel().rows?.length">
            <TableRow
              v-for="row in table.getRowModel().rows" :key="row.id"
              :data-state="row.getIsSelected() ? 'selected' : undefined"
            >
              <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
              </TableCell>
            </TableRow>
          </template>
          <template v-else>``
            <TableRow>
              <TableCell :colspan="columns.length" class="h-24 text-center">
                No results.
              </TableCell>
            </TableRow>
          </template>
        </TableBody>
      </Table>
    </div>

    <div>
    <div class="flex items-center justify-end py-4 space-x-2">
      <OrderTablePagination :table="table" />
    </div>
  </div>
  </div>
</template>