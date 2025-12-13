<script setup lang="ts">
import { computed } from 'vue'
import type { Table } from '@tanstack/vue-table'
import { X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import DataTableViewOptions from './DataTableViewOptions.vue'
import { Trash2, RotateCcw, RefreshCw, Plus } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { ref } from 'vue'
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
const emit = defineEmits<{
  add: []
}>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
const selectedRows = computed(() => props.table.getFilteredSelectedRowModel().rows)
const hasSelection = computed(() => selectedRows.value.length > 0)
const hasInactiveSelection = computed(() => 
  selectedRows.value.some(row => row.original.deleted_at)
)

const showBulkDeleteDialog = ref(false)
const showBulkRestoreDialog = ref(false)

const handleBulkDelete = () => {
  const ids = selectedRows.value
    .filter(row => !row.original.deleted_at)
    .map(row => row.original.id)

  if (ids.length === 0) {
    toast.error('Please select active branches to delete')
    return
  }

  router.post(route('branches.bulk-destroy'), { ids }, {
    preserveScroll: true,
    onSuccess: () => {
      props.table.resetRowSelection()
      showBulkDeleteDialog.value = false
      toast.success('Branches deleted successfully')
    },
    onError: (errors) => {
      showBulkDeleteDialog.value = false
      toast.error(errors.message || 'Failed to delete branches')
    },
  })
}

const handleBulkRestore = () => {
  const ids = selectedRows.value
    .filter(row => row.original.deleted_at)
    .map(row => row.original.id)

  if (ids.length === 0) {
    toast.error('Please select inactive branches to restore')
    return
  }

  router.post(route('branches.bulk-restore'), { ids }, {
    preserveScroll: true,
    onSuccess: () => {
      props.table.resetRowSelection()
      showBulkRestoreDialog.value = false
      toast.success('Branches restored successfully')
    },
    onError: (errors) => {
      showBulkRestoreDialog.value = false
      toast.error(errors.message || 'Failed to restore branches')
    },
  })
}

const handleRefresh = () => {
  router.reload({ only: ['branches'] })
  toast.success('Branches refreshed')
}
</script>

<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 items-center space-x-2">
      <Input
        placeholder="Search branches..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
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
        variant="destructive"
        size="sm"
        class="h-8"
        @click="showBulkDeleteDialog = true"
      >
        <Trash2 class="mr-2 h-4 w-4" />
        Delete {{ selectedRows.length }} branch(es)
      </Button>

      <Button
        v-if="hasInactiveSelection"
        variant="default"
        size="sm"
        class="h-8 bg-green-600 hover:bg-green-700"
        @click="showBulkRestoreDialog = true"
      >
        <RotateCcw class="mr-2 h-4 w-4" />
        Restore
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

    <div class="flex items-center space-x-2">
      <Button
        variant="default"
        size="sm"
        class="h-8"
        @click="emit('add')"
      >
        <Plus class="mr-2 h-4 w-4" />
        Add Branch
      </Button>
      <DataTableViewOptions :table="table" />
    </div>

    <!-- Bulk Delete Confirmation Dialog -->
    <AlertDialog v-model:open="showBulkDeleteDialog">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete Branches?</AlertDialogTitle>
          <AlertDialogDescription>
            This will delete {{ selectedRows.filter(row => !row.original.deleted_at).length }} branch(es).
            Branches with devices or users assigned cannot be deleted.
            This action cannot be undone.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction @click="handleBulkDelete" class="bg-destructive hover:bg-destructive/90">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>

    <!-- Bulk Restore Confirmation Dialog -->
    <AlertDialog v-model:open="showBulkRestoreDialog">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Restore Branches?</AlertDialogTitle>
          <AlertDialogDescription>
            This will restore {{ selectedRows.filter(row => row.original.deleted_at).length }} branch(es).
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction @click="handleBulkRestore" class="bg-green-600 hover:bg-green-700">
            Restore
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </div>
</template>
