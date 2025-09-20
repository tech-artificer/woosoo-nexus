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
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog"
import { ref, computed } from 'vue'
import type { User } from '@/types/models';
import UserForm from '@/components/users/UserForm.vue';
import { toast } from 'vue-sonner'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

interface DataTableRowActionsProps {
  row: Row<User>
}
const props = defineProps<DataTableRowActionsProps>()
const computedUser = computed(() => {
  const parsed = props.row.original
  return {
    ...parsed as User
  }
})
// control sheet open state
const isSheetOpen = ref(false)
// const isRestoring = ref(null)
const openSheet = () => {
  isSheetOpen.value = true
}

const showDialog = ref(false);
const openDialog = () => {
  showDialog.value = true
}

const deactivateAccount = (computedUser: User) => {
  router.visit(route('users.destroy', computedUser), {
    method: 'delete',
    onSuccess: () => {
     toast.warning('Account Deactivated')
    }
  })
}


const restoreUser = (computedUser: User) => {
  // isRestoring.value = computedUser.id
  
  router.patch(route('users.restore', computedUser.id), {}, {
    onSuccess: (page) => {
      // isRestoring.value = null
      // showNotification('success', `${computedUser.name} has been restored successfully.`)
    },
    onError: (errors) => {
      // isRestoring.value = null
      // showNotification('error', 'Failed to restore user. Please try again.')
    }
  })
}

console.log(route('users.restore', 3))
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

      <DropdownMenuItem class="cursor-pointer" @click="openSheet">Edit User</DropdownMenuItem>
      
        
      <DropdownMenuItem 
        @click="openDialog"
        :disabled="computedUser.role as any === 'Owner'" 
        v-if="!computedUser.deleted_at" 
        class="text-orange cursor-pointer"
        >
        Deactivate account 
      </DropdownMenuItem>
      

      <DropdownMenuItem 
        @click="restoreUser(computedUser)"
        v-else-if="computedUser.deleted_at && computedUser.role !== 'Administrator'"
        class="text-green cursor-pointer">
        Activate account
        <DropdownMenuSeparator />
      </DropdownMenuItem>


    </DropdownMenuContent>
  </DropdownMenu>

  <AlertDialog v-model:open="showDialog">
    <!-- <AlertDialogTrigger as-child>
      <Button variant="outline">
        Show Dialog
      </Button>
    </AlertDialogTrigger> -->
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Are you absolutely sure?</AlertDialogTitle>
        <AlertDialogDescription>
          This action cannot be undone. This will permanently delete this
          account.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction class="bg-red-600 hover:bg-red-500" @click="deactivateAccount(computedUser)">Continue</AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>


  <Sheet v-model:open="isSheetOpen">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit User</SheetTitle>
        <SheetDescription>
          Edit the user's information.
        </SheetDescription>
      </SheetHeader>
      <UserForm :user="computedUser" form-type="edit" />
    </SheetContent>
  </Sheet>


</template>
