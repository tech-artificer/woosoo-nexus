<script setup lang="ts">
// import type { Row } from '@tanstack/vue-table'
import { Ellipsis } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
// import { router } from '@inertiajs/vue3'
import {
  DropdownMenu,
  DropdownMenuContent,
  // DropdownMenuItem,
  // DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
// import {
//   AlertDialog,
//   AlertDialogAction,
//   AlertDialogCancel,
//   AlertDialogContent,
//   AlertDialogDescription,
//   AlertDialogFooter,
//   AlertDialogHeader,
//   AlertDialogTitle,
//   AlertDialogTrigger,
// } from "@/components/ui/alert-dialog"
// import { ref, computed } from 'vue'
// import type { Menu } from '@/types/models';
// import MenuForm from '@/components/Menus/MenuForm.vue';
// import { toast } from 'vue-sonner'
// import {
//   Sheet,
//   SheetContent,
//   SheetDescription,
//   SheetHeader,
//   SheetTitle,
// } from '@/components/ui/sheet'

// interface DataTableRowActionsProps {
//   row: Row<Menu>
// }
// const props = defineProps<DataTableRowActionsProps>()
// const computedMenu = computed(() => {
//   const parsed = props.row.original
//   return {
//     ...parsed as Menu
//   }
// })
// control sheet open state
// const isSheetOpen = ref(false)
// // const isRestoring = ref(null)
// const openSheet = () => {
//   isSheetOpen.value = true
// }

// const showDialog = ref(false);
// const openDialog = () => {
//   showDialog.value = true
// }

// const deactivateAccount = (computedMenu: Menu) => {
//   router.visit(route('Menus.destroy', computedMenu), {
//     method: 'delete',
//     onSuccess: () => {
//      toast.warning('Account Deactivated')
//     }
//   })
// }

// const restoreMenu = (computedMenu: Menu) => {
//   // isRestoring.value = computedMenu.id
  
//   router.patch(route('Menus.restore', computedMenu.id), {}, {
//     // onSuccess: (page) => {
//     //   // isRestoring.value = null
//     //   // showNotification('success', `${computedMenu.name} has been restored successfully.`)
//     // },
//     // onError: (errors) => {
//     //   // isRestoring.value = null
//     //   // showNotification('error', 'Failed to restore Menu. Please try again.')
//     // }
//   })
// }

console.log(route('Menus.restore', 3))
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

      <!-- <DropdownMenuItem class="cursor-pointer" @click="openSheet">Edit Menu</DropdownMenuItem> -->
      
        
      <!-- <DropdownMenuItem 
        @click="openDialog"
        :disabled="computedMenu.role as any === 'Owner'" 
        v-if="!computedMenu.deleted_at" 
        class="text-orange cursor-pointer"
        >
        Deactivate account 
      </DropdownMenuItem> -->
      

      <!-- <DropdownMenuItem 
        @click="restoreMenu(computedMenu)"
        v-else-if="computedMenu.deleted_at && computedMenu.role !== 'Administrator'"
        class="text-green cursor-pointer">
        Activate account
        <DropdownMenuSeparator />
      </DropdownMenuItem> -->


    </DropdownMenuContent>
  </DropdownMenu>

  <!-- <AlertDialog v-model:open="showDialog">
    <AlertDialogTrigger as-child>
      <Button variant="outline">
        Show Dialog
      </Button>
    </AlertDialogTrigger>
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
        <AlertDialogAction class="bg-red-600 hover:bg-red-500" @click="deactivateAccount(computedMenu)">Continue</AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog> -->


  <!-- <Sheet v-model:open="isSheetOpen">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit Menu</SheetTitle>
        <SheetDescription>
          Edit the Menu's information.
        </SheetDescription>
      </SheetHeader>
    
    </SheetContent>
  </Sheet> -->


</template>
