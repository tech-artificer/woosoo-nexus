<script setup lang="ts">
import { MoreHorizontal, Pen, Trash2, RotateCcw } from 'lucide-vue-next'
import { Row } from '@tanstack/vue-table'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Branch } from './columns'
import { router } from '@inertiajs/vue3'

interface DataTableRowActionsProps {
  row: Row<Branch>
}

const props = defineProps<DataTableRowActionsProps>()

const emit = defineEmits<{
  edit: [branch: Branch]
}>()

const handleEdit = () => {
  emit('edit', props.row.original)
}

const handleDelete = () => {
  if (confirm(`Are you sure you want to delete "${props.row.original.name}"?`)) {
    router.delete(route('branches.destroy', props.row.original.id), {
      preserveScroll: true,
    })
  }
}

const handleRestore = () => {
  if (confirm(`Restore "${props.row.original.name}"?`)) {
    router.patch(route('branches.restore', props.row.original.id), {}, {
      preserveScroll: true,
    })
  }
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button
        variant="ghost"
        class="flex h-8 w-8 p-0 data-[state=open]:bg-muted"
      >
        <MoreHorizontal class="h-4 w-4" />
        <span class="sr-only">Open menu</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[160px]">
      <DropdownMenuItem @click="handleEdit">
        <Pen class="mr-2 h-4 w-4" />
        Edit
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem 
        v-if="!row.original.deleted_at"
        @click="handleDelete"
        class="text-red-600"
      >
        <Trash2 class="mr-2 h-4 w-4" />
        Delete
      </DropdownMenuItem>
      <DropdownMenuItem 
        v-else
        @click="handleRestore"
        class="text-green-600"
      >
        <RotateCcw class="mr-2 h-4 w-4" />
        Restore
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>
</template>
