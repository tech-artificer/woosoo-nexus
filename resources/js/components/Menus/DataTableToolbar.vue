<script setup lang="ts">
import { computed, ref } from 'vue'
import type { Table } from '@tanstack/vue-table'
import { X, RefreshCw, CheckCircle, XCircle } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import DataTableFacetedFilter from './DataTableFacetedFilter.vue'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
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

interface DataTableToolbarProps {
  table: Table<any>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
const selectedRows = computed(() => props.table.getFilteredSelectedRowModel().rows)
const hasSelection = computed(() => selectedRows.value.length > 0)

const showEnableDialog = ref(false)
const showDisableDialog = ref(false)

const categoryOptions = computed(() => {
  const col = props.table.getColumn('category')
  const facets = col?.getFacetedUniqueValues()
  if (!facets) return []
  return Array.from(facets.keys())
    .filter(v => v)
    .map((v: any) => ({ label: String(v), value: String(v) }))
})

const availabilityOptions = [
  { label: 'Available', value: 'true' },
  { label: 'Unavailable', value: 'false' },
]

const handleBulkToggle = (isAvailable: boolean) => {
  const ids = selectedRows.value.map(row => row.original.id)

  router.post(route('menus.bulk-toggle-availability'), { ids, is_available: isAvailable }, {
    preserveScroll: true,
    onSuccess: () => {
      props.table.resetRowSelection()
      if (isAvailable) {
        showEnableDialog.value = false
        toast.success('Menus enabled successfully')
      } else {
        showDisableDialog.value = false
        toast.success('Menus disabled successfully')
      }
    },
    onError: (errors) => {
      showEnableDialog.value = false
      showDisableDialog.value = false
      toast.error(errors.message || 'Failed to update menus')
    },
  })
}

const handleRefresh = () => {
  router.reload({ only: ['menus'] })
  toast.success('Menus refreshed')
}
</script>

<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 flex-wrap items-center gap-2">
      <Input
        placeholder="Search menus..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
      />

      <DataTableFacetedFilter
        v-if="table.getColumn('category')"
        :column="table.getColumn('category')"
        title="Category"
        :options="categoryOptions"
      />

      <DataTableFacetedFilter
        v-if="table.getColumn('is_available')"
        :column="table.getColumn('is_available')"
        title="Availability"
        :options="availabilityOptions"
      />

      <Button
        v-if="isFiltered"
        variant="ghost"
        class="h-8 px-2 lg:px-3"
        @click="table.resetColumnFilters()"
      >
        Reset
        <X class="ml-2 h-4 w-4" />
      </Button>

      <!-- Bulk Actions -->
      <Button
        v-if="hasSelection"
        variant="default"
        size="sm"
        class="h-8 bg-green-600 hover:bg-green-700"
        @click="showEnableDialog = true"
      >
        <CheckCircle class="mr-2 h-4 w-4" />
        Enable {{ selectedRows.length }}
      </Button>

      <Button
        v-if="hasSelection"
        variant="destructive"
        size="sm"
        class="h-8"
        @click="showDisableDialog = true"
      >
        <XCircle class="mr-2 h-4 w-4" />
        Disable {{ selectedRows.length }}
      </Button>

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

  <!-- Enable Confirmation Dialog -->
  <AlertDialog v-model:open="showEnableDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Enable Menus?</AlertDialogTitle>
        <AlertDialogDescription>
          This will make {{ selectedRows.length }} menu(s) available for ordering.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction @click="handleBulkToggle(true)" class="bg-green-600 hover:bg-green-700">
          Enable
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>

  <!-- Disable Confirmation Dialog -->
  <AlertDialog v-model:open="showDisableDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Disable Menus?</AlertDialogTitle>
        <AlertDialogDescription>
          This will make {{ selectedRows.length }} menu(s) unavailable for ordering.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction @click="handleBulkToggle(false)" class="bg-destructive hover:bg-destructive/90">
          Disable
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
