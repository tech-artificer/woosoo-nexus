<script setup lang="ts">
import type { Row } from '@tanstack/vue-table'
import { Ellipsis } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/vue3'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
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
import { ref, computed } from 'vue'
import RoleForm from '@/components/Roles/RoleForm.vue'
import { toast } from 'vue-sonner'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

export interface Role {
  id: number
  name: string
  guard_name: string
  permissions_count: number
  users_count: number
  permissions?: Array<{ id: number; name: string }>
  created_at: string
  updated_at: string
}

interface DataTableRowActionsProps {
  row: Row<Role>
}

const props = defineProps<DataTableRowActionsProps>()

const computedRole = computed(() => props.row.original)

const isSheetOpen = ref(false)
const showDeleteDialog = ref(false)

const openSheet = () => {
  isSheetOpen.value = true
}

const openDeleteDialog = () => {
  showDeleteDialog.value = true
}

const deleteRole = (role: Role) => {
  router.delete(route('roles.destroy', role.id), {
    onSuccess: () => {
      toast.success('Role deleted successfully')
      showDeleteDialog.value = false
    },
    onError: () => {
      toast.error('Failed to delete role')
    }
  })
}
</script>

<template>
  <DropdownMenu>
    <DropdownMenuTrigger as-child>
      <Button variant="ghost" class="flex h-8 w-8 p-0 data-[state=open]:bg-muted">
        <Ellipsis class="h-4 w-4" />
        <span class="sr-only">Open menu</span>
      </Button>
    </DropdownMenuTrigger>
    <DropdownMenuContent align="end" class="w-[160px]">
      <DropdownMenuItem class="cursor-pointer" @click="openSheet">
        Edit Role
      </DropdownMenuItem>
      <DropdownMenuSeparator />
      <DropdownMenuItem 
        @click="openDeleteDialog"
        class="text-destructive cursor-pointer"
      >
        Delete Role
      </DropdownMenuItem>
    </DropdownMenuContent>
  </DropdownMenu>

  <AlertDialog v-model:open="showDeleteDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
        <AlertDialogDescription>
          This action cannot be undone. This will permanently delete the role
          "{{ computedRole.name }}" and remove it from all users.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction 
          class="bg-destructive hover:bg-destructive/90" 
          @click="deleteRole(computedRole)"
        >
          Delete
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>

  <Sheet v-model:open="isSheetOpen">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit Role</SheetTitle>
        <SheetDescription>
          Update role details and permissions
        </SheetDescription>
      </SheetHeader>
      <RoleForm :role="computedRole" @close="isSheetOpen = false" />
    </SheetContent>
  </Sheet>
</template>
