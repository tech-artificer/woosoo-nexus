<script setup lang="ts">
import type { Row } from '@tanstack/vue-table'
import { Button } from '@/components/ui/button'
import { router } from '@inertiajs/vue3'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
  DropdownMenuLabel
} from '@/components/ui/dropdown-menu'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog"

import { MoreHorizontal, Printer } from 'lucide-vue-next'
import { ref, computed } from 'vue'
import type { DeviceOrder } from '@/types/models';
import { toast } from 'vue-sonner'
import KitchenTicket from '@/components/KitchenTicket.vue'

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
// const isSheetOpen = ref(false)

// const isRestoring = ref(null)
// const openSheet = () => {
//   isSheetOpen.value = true
// }

// const showDialog = ref(false);
// const openVoidDialog = () => {
//   showVoidDialog.value = true
// }


const voidOrder = () => {
  router.delete(`/orders/${computedOrder.value.id}`, {
    
    onSuccess: () => {
      showVoidDialog.value = false
      toast.success('Order Voided')
    }
  })
}

const printOrder = (order_id: number | string) => {

  router.post(`/orders/print`, { 
    order_id: order_id, 
  }, { 
    onSuccess: () => {
      toast.success('Order Sent to Printer')
    }
  })
}

const completeOrder = (order_id: number | string) => {

  router.post(`/orders/complete`, { 
    order_id: order_id, 
  }, { 
    onSuccess: () => {
      toast.success('Order Completed')
    }
  })
}

</script>

<template>

<DropdownMenu>
  <DropdownMenuTrigger as-child>
    <Button variant="ghost" class="h-8 w-8 p-0">
      <span class="sr-only">Open menu</span>
      <MoreHorizontal class="h-4 w-4" />
    </Button>
  </DropdownMenuTrigger>
  <DropdownMenuContent align="end">
    
    <DropdownMenuLabel>Actions</DropdownMenuLabel>
    <DropdownMenuItem @click="printOrder(computedOrder.order_id as number | string)">
      Print
    </DropdownMenuItem>
    <DropdownMenuItem @click="completeOrder(computedOrder.order_id as number | string)">
      Complete
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem @click="voidOrder">
      Void
    </DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>

 <Dialog>
    <DialogTrigger as-child>
      <Button variant="ghost" class="p-1">
        <Printer />
      </Button>
    </DialogTrigger>
   
    <DialogContent class="w-fit">
       <DialogTitle class="sr-only" aria-describedby="kitchen-ticket">Kitchen Ticket</DialogTitle>
        <DialogDescription>Print Order Items</DialogDescription> 
      <div>
        <KitchenTicket
        :item-data="computedOrder.items"
      />
      </div>
      <DialogFooter>
       <Button>Print</Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
