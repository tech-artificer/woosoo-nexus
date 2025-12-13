<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import { computed, ref } from 'vue'
import { X, Trash2 } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import DataTableFacetedFilter from './DataTableFacetedFilter.vue'
import DataTableViewOptions from './DataTableViewOptions.vue'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog"

interface DataTableToolbarProps {
  table: Table<any>
}

const props = defineProps<DataTableToolbarProps>()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
const selectedRows = computed(() => props.table.getFilteredSelectedRowModel().rows)
const hasSelection = computed(() => selectedRows.value.length > 0)

const showBulkDeleteDialog = ref(false)

const guards = [
  { label: 'Web', value: 'web' },
  { label: 'API', value: 'api' },
]

const handleBulkDelete = () => {
  const ids = selectedRows.value.map(row => row.original.id)
  
  router.post(route('roles.bulk-destroy'), { ids }, {
    onSuccess: () => {
      toast.success(`${ids.length} role(s) deleted successfully`)
      props.table.resetRowSelection()
      showBulkDeleteDialog.value = false
    },
    onError: (errors) => {
      if (errors.bulk_delete) {
        const errorMessages = Array.isArray(errors.bulk_delete) 
          ? errors.bulk_delete.join(', ') 
          : errors.bulk_delete
        toast.error(errorMessages)
      } else {
        toast.error('Failed to delete roles')
      }
      showBulkDeleteDialog.value = false
    }
  })
}

</script>

<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 items-center space-x-2">
      <Input
        placeholder="Filter roles..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
      />
      <DataTableFacetedFilter
        v-if="table.getColumn('guard_name')"
        :column="table.getColumn('guard_name')"
        title="Guard"
        :options="guards"
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
      
      <Button
        v-if="hasSelection"
        variant="destructive"
        size="sm"
        class="h-8 px-2 lg:px-3"
        @click="showBulkDeleteDialog = true"
      >
        <Trash2 class="mr-2 h-4 w-4" />
        Delete {{ selectedRows.length }} role(s)
      </Button>
    </div>
    <DataTableViewOptions :table="table" />
  </div>

  <AlertDialog v-model:open="showBulkDeleteDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
        <AlertDialogDescription>
          This will permanently delete {{ selectedRows.length }} role(s).
          Roles with assigned users will be skipped.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction 
          class="bg-destructive hover:bg-destructive/90" 
          @click="handleBulkDelete"
        >
          Delete
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>
