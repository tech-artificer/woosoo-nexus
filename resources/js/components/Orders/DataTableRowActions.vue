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
import type { DeviceOrder } from '@/types/models';
// import UserForm from '@/components/Orders/UserForm.vue';
import { toast } from 'vue-sonner'
import { usePage } from '@inertiajs/vue3'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

const page = usePage();

interface DataTableRowActionsProps {
  row: Row<DeviceOrder>
}
const props = defineProps<DataTableRowActionsProps>()
const computedOrder = computed(() => {
  const parsed = props.row.original
  return {
    ...parsed as DeviceOrder
  }
})

const showVoidDialog = ref(false);
// control sheet open state
const isSheetOpen = ref(false)

// const isRestoring = ref(null)
const openSheet = () => {
  isSheetOpen.value = true
}

const showDialog = ref(false);
const openVoidDialog = () => {
  showVoidDialog.value = true
}


const voidOrder = () => {
  console.log(computedOrder.value.id)
  router.delete(`/orders/${computedOrder.value.id}`, {
    
    onSuccess: () => {
      showVoidDialog.value = false
      toast.success('Order Voided')
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

        <!-- <DropdownMenuItem class="cursor-pointer" @click="openSheet">Edit User</DropdownMenuItem> -->
      
        <!-- <DropdownMenuItem 
          @click=""
          v-if="!computedOrder.deleted_at" 
          class="text-orange cursor-pointer"
          >
          Print 
        </DropdownMenuItem> -->
          
        <DropdownMenuItem 
          @click="openVoidDialog"
          v-if="!computedOrder.deleted_at" 
          class="text-orange cursor-pointer"
          >
          Void Order
        </DropdownMenuItem>
    
     

    </DropdownMenuContent>
  </DropdownMenu>

  <AlertDialog v-model:open="showVoidDialog">
    <!-- <AlertDialogTrigger as-child>
      <Button variant="outline">
        Show Dialog
      </Button>
    </AlertDialogTrigger> -->
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Void Order Number: {{ computedOrder.order_number }}
            <div class="block mt-2"> Are you sure? </div></AlertDialogTitle>
        <AlertDialogDescription>
          This action cannot be undone. This will [<span class="text-red-700 font-bold">void</span>] this transaction.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel>Cancel</AlertDialogCancel>
        <AlertDialogAction class="bg-red-600 text-white hover:bg-red-500" @click="voidOrder">Continue</AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>


  <!-- <Sheet v-model:open="isSheetOpen">
    <SheetContent>
      <SheetHeader>
        <SheetTitle>Edit User</SheetTitle>
        <SheetDescription>
          Edit the user's information.
        </SheetDescription>
      </SheetHeader>
      <UserForm :user="computedOrder" form-type="edit" />
    </SheetContent>
  </Sheet> -->


</template>
