<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import { RefreshCw, CheckCircle, XCircle, Download } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { computed, ref, watch } from 'vue'
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
  serverFilters?: any
  devices?: any[]
  tables?: any[]
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)

const selectedRows = computed(() => {
  return props.table.getFilteredSelectedRowModel().rows.map(row => row.original)
})

// Server-side filter mode if `serverFilters` prop is present (explicitly provided by Inertia)
const serverMode = computed(() => props.serverFilters !== undefined)

// Server-side reactive values
const serverStatus = ref<string | null>(serverMode.value && props.serverFilters?.status ? (Array.isArray(props.serverFilters.status) ? props.serverFilters.status.join(',') : String(props.serverFilters.status)) : null)
const serverDeviceId = ref<string | number | null>(serverMode.value ? props.serverFilters?.device_id ?? null : null)
const serverTableId = ref<string | number | null>(serverMode.value ? props.serverFilters?.table_id ?? null : null)
const serverSearch = ref<string>(serverMode.value ? props.serverFilters?.search ?? '' : '')

let serverTimer: ReturnType<typeof setTimeout> | null = null

const applyServerFilters = () => {
  const params: any = {}
  if (serverStatus.value) params.status = serverStatus.value
  if (serverDeviceId.value) params.device_id = serverDeviceId.value
  if (serverTableId.value) params.table_id = serverTableId.value
  if (serverSearch.value) params.search = serverSearch.value
  router.get(route('orders.index'), params, { preserveState: true, replace: true })
}

const debounceApply = () => {
  if (serverTimer) clearTimeout(serverTimer)
  serverTimer = setTimeout(() => applyServerFilters(), 300)
}

watch(serverStatus, () => debounceApply())
watch(serverDeviceId, () => debounceApply())
watch(serverTableId, () => debounceApply())
watch(serverSearch, () => debounceApply())

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

      <!-- Server-mode filters (Inertia server filtering) -->
      <template v-if="serverMode">
        <Input
          placeholder="Search order #..."
          :model-value="serverSearch"
          class="h-8 w-[150px] lg:w-[200px]"
          @input="(e) => serverSearch = e.target.value"
        />

        <select v-model="serverStatus" class="h-8 px-2 rounded border">
          <option value="">All Statuses</option>
          <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>

        <select v-if="props.devices" v-model="serverDeviceId" class="h-8 px-2 rounded border">
          <option value="">All Devices</option>
          <option v-for="d in props.devices" :key="d.id" :value="d.id">{{ d.name }}</option>
        </select>

        <select v-if="props.tables" v-model="serverTableId" class="h-8 px-2 rounded border">
          <option value="">All Tables</option>
          <option v-for="t in props.tables" :key="t.id" :value="t.id">{{ t.name }}</option>
        </select>
      </template>

      <!-- Client-mode filters (existing column-based filters) -->
      <template v-else>
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
      </template>

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
