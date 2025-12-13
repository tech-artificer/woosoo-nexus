<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import { RefreshCw, CheckCircle, XCircle, Download } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { computed, ref } from 'vue'
import DataTableFacetedFilter from '@/components/Orders/DataTableFacetedFilter.vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

interface DataTableToolbarProps {
  table: Table<any>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)

const selectedRows = computed(() => {
  return props.table.getFilteredSelectedRowModel().rows.map(row => row.original)
})

// Status options for filtering
const statusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Ready', value: 'ready' },
  { label: 'Served', value: 'served' },
  { label: 'Completed', value: 'completed' },
  { label: 'Cancelled', value: 'cancelled' },
  { label: 'Voided', value: 'voided' },
]

// Dialog states
const showCompleteDialog = ref(false)
const showVoidDialog = ref(false)

const handleBulkComplete = () => {
  const orderIds = selectedRows.value.map((order: any) => order.order_id)
  
  router.post(route('orders.bulk-complete'), {
    order_ids: orderIds,
  }, {
    onSuccess: () => {
      showCompleteDialog.value = false
      props.table.resetRowSelection()
      toast.success(`${orderIds.length} order(s) completed successfully`)
    },
    onError: () => {
      toast.error('Failed to complete orders')
    },
  })
}

const handleBulkVoid = () => {
  const ids = selectedRows.value.map((order: any) => order.id)
  
  router.post(route('orders.bulk-void'), {
    ids: ids,
  }, {
    onSuccess: () => {
      showVoidDialog.value = false
      props.table.resetRowSelection()
      toast.success(`${ids.length} order(s) voided successfully`)
    },
    onError: () => {
      toast.error('Failed to void orders')
    },
  })
}

const handleRefresh = () => {
  router.reload({ only: ['orders', 'orderHistory', 'stats'] })
  toast.success('Orders refreshed')
}

const handleExport = () => {
  // Get filtered data
  const filteredData = props.table.getFilteredRowModel().rows.map(row => row.original)
  
  // Convert to CSV
  const headers = ['Order #', 'Device', 'Table', 'Guests', 'Total', 'Status', 'Created']
  const rows = filteredData.map((order: any) => {
    const total = Array.isArray(order.items) 
      ? order.items.reduce((acc: number, item: any) => 
          acc + (Number(item?.price) || 0) * (Number(item?.quantity) || 0) + (Number(item?.tax) || 0), 0)
      : 0
    
    return [
      order.order_number,
      order.device?.name || '',
      order.table?.name || '',
      order.guest_count || '',
      total.toFixed(2),
      order.status,
      order.created_at ? new Date(order.created_at).toLocaleString() : '',
    ]
  })
  
  const csv = [headers, ...rows].map(row => row.join(',')).join('\n')
  
  // Download
  const blob = new Blob([csv], { type: 'text/csv' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `orders-${new Date().toISOString().split('T')[0]}.csv`
  link.click()
  URL.revokeObjectURL(url)
  
  toast.success('Orders exported to CSV')
}
</script>

<template>
  <div class="flex flex-row flex-wrap justify-between gap-2">
    <div class="flex flex-row flex-wrap gap-2">
      <!-- Search by order number -->
      <Input
        placeholder="Search order #..."
        :model-value="(table.getColumn('order_number')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[200px]"
        @input="table.getColumn('order_number')?.setFilterValue($event.target.value)"
      />

      <!-- Status filter -->
      <DataTableFacetedFilter
        v-if="table.getColumn('status')"
        :column="table.getColumn('status')"
        title="Status"
        :options="statusOptions"
      />

      <!-- Reset filters -->
      <Button
        v-if="isFiltered"
        variant="ghost"
        class="h-8 px-2 lg:px-3"
        @click="table.resetColumnFilters()"
      >
        Reset
        <RefreshCw class="ml-2 h-4 w-4" />
      </Button>
    </div>

    <div class="flex flex-row flex-wrap gap-2">
      <!-- Bulk Complete -->
      <Button
        v-if="selectedRows.length > 0"
        variant="default"
        size="sm"
        class="h-8 bg-green-600 hover:bg-green-700"
        @click="showCompleteDialog = true"
      >
        <CheckCircle class="mr-2 h-4 w-4" />
        Complete ({{ selectedRows.length }})
      </Button>

      <!-- Bulk Void -->
      <Button
        v-if="selectedRows.length > 0"
        variant="destructive"
        size="sm"
        class="h-8"
        @click="showVoidDialog = true"
      >
        <XCircle class="mr-2 h-4 w-4" />
        Void ({{ selectedRows.length }})
      </Button>

      <!-- Export -->
      <Button
        variant="outline"
        size="sm"
        class="h-8"
        @click="handleExport"
      >
        <Download class="mr-2 h-4 w-4" />
        Export
      </Button>

      <!-- Refresh -->
      <Button
        variant="outline"
        size="sm"
        class="h-8"
        @click="handleRefresh"
      >
        <RefreshCw class="h-4 w-4" />
      </Button>
    </div>
  </div>

  <!-- Complete Confirmation Dialog -->
  <AlertDialog v-model:open="showCompleteDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Complete Orders</AlertDialogTitle>
        <AlertDialogDescription>
          Are you sure you want to complete {{ selectedRows.length }} order(s)?
          This action will mark them as completed and move them to order history.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction
          class="bg-green-600 hover:bg-green-700"
          @click="handleBulkComplete"
        >
          Complete Orders
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>

  <!-- Void Confirmation Dialog -->
  <AlertDialog v-model:open="showVoidDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Void Orders</AlertDialogTitle>
        <AlertDialogDescription>
          Are you sure you want to void {{ selectedRows.length }} order(s)?
          This action cannot be undone and will mark the orders as voided.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction
          class="bg-red-600 hover:bg-red-700"
          @click="handleBulkVoid"
        >
          Void Orders
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
