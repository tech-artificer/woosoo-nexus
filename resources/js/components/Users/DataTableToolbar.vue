<script setup lang="ts">
import type { Table } from '@tanstack/vue-table'
import type { User } from '@/types/models'
import { computed, ref } from 'vue'
import { X, Trash2, RefreshCcw, RotateCcw } from 'lucide-vue-next'
import { router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import DataTableFacetedFilter from './DataTableFacetedFilter.vue'
import AddUser from './Register.vue'
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
  table: Table<User>
}

const props = defineProps<DataTableToolbarProps>()
const page = usePage()

const isFiltered = computed(() => props.table.getState().columnFilters.length > 0)
const selectedRows = computed(() => props.table.getFilteredSelectedRowModel().rows)
const hasSelection = computed(() => selectedRows.value.length > 0)
const hasInactiveSelection = computed(() => 
  selectedRows.value.some(row => row.original.deleted_at)
)

const showBulkDeleteDialog = ref(false)
const showBulkRestoreDialog = ref(false)

// Get roles from page props for filters
const rolesFromPage = computed(() => (page.props as any).roles || [])

const roleOptions = computed(() => 
  rolesFromPage.value.map((role: any) => ({
    label: role.name,
    value: role.name
  }))
)

const handleBulkDelete = () => {
  const ids = selectedRows.value
    .filter(row => !row.original.deleted_at)
    .map(row => row.original.id)
  
  if (ids.length === 0) {
    toast.error('No active users selected')
    showBulkDeleteDialog.value = false
    return
  }
  
  router.post(route('users.bulk-destroy'), { ids }, {
    preserveState: true,
    onSuccess: () => {
      toast.warning(`${ids.length} user(s) deactivated`)
      props.table.resetRowSelection()
      showBulkDeleteDialog.value = false
    },
    onError: () => {
      toast.error('Failed to deactivate users')
      showBulkDeleteDialog.value = false
    }
  })
}

const handleBulkRestore = () => {
  const ids = selectedRows.value
    .filter(row => row.original.deleted_at)
    .map(row => row.original.id)
  
  if (ids.length === 0) {
    toast.error('No inactive users selected')
    showBulkRestoreDialog.value = false
    return
  }
  
  router.post(route('users.bulk-restore'), { ids }, {
    preserveState: true,
    onSuccess: () => {
      toast.success(`${ids.length} user(s) restored`)
      props.table.resetRowSelection()
      showBulkRestoreDialog.value = false
    },
    onError: () => {
      toast.error('Failed to restore users')
      showBulkRestoreDialog.value = false
    }
  })
}

const handleRefresh = () => {
  router.reload({ only: ['users'] })
  toast.success('Refreshed')
}
</script>

<template>
  <div class="flex items-center justify-between">
    <div class="flex flex-1 flex-wrap items-center gap-2">
      <Input
        placeholder="Filter users..."
        :model-value="(table.getColumn('name')?.getFilterValue() as string) ?? ''"
        class="h-8 w-[150px] lg:w-[250px]"
        @input="table.getColumn('name')?.setFilterValue($event.target.value)"
      />
      
      <DataTableFacetedFilter
        v-if="table.getColumn('role') && roleOptions.length > 0"
        :column="table.getColumn('role')"
        title="Role"
        :options="roleOptions"
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
        Deactivate {{ selectedRows.length }}
      </Button>
      
      <Button
        v-if="hasInactiveSelection"
        variant="outline"
        size="sm"
        class="h-8 px-2 lg:px-3 text-green-600"
        @click="showBulkRestoreDialog = true"
      >
        <RotateCcw class="mr-2 h-4 w-4" />
        Restore
      </Button>
    </div>
    
    <div class="flex items-center gap-2">
      <Button
        variant="outline"
        size="sm"
        class="h-8 px-2 lg:px-3"
        @click="handleRefresh"
      >
        <RefreshCcw class="h-4 w-4" />
      </Button>
      <AddUser />
    </div>
  </div>

  <AlertDialog v-model:open="showBulkDeleteDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Deactivate Users?</AlertDialogTitle>
        <AlertDialogDescription>
          This will deactivate {{ selectedRows.filter(r => !r.original.deleted_at).length }} user account(s).
          They can be restored later.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction 
          class="bg-destructive hover:bg-destructive/90" 
          @click="handleBulkDelete"
        >
          Deactivate
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>

  <AlertDialog v-model:open="showBulkRestoreDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Restore Users?</AlertDialogTitle>
        <AlertDialogDescription>
          This will restore {{ selectedRows.filter(r => r.original.deleted_at).length }} user account(s).
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction 
          class="bg-green-600 hover:bg-green-700" 
          @click="handleBulkRestore"
        >
          Restore
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>

