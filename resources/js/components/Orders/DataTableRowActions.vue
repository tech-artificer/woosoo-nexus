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
const showPosDialog = ref(false);
const posDialogOrderId = ref<number | string | null>(null);
const posDialogSessionId = ref<number | null>(null);
const showViewDialog = ref(false);
const viewDialogOrder = ref<any | null>(null);
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

const openPosDialog = (order_id: number | string) => {
  posDialogOrderId.value = order_id
  // fetch latest session from API and prefill session id
  fetch('/api/session/latest', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.ok ? r.json() : Promise.reject(r))
    .then(json => {
      const session = json?.session ?? null
      if (session && session.id) posDialogSessionId.value = session.id
      showPosDialog.value = true
    })
    .catch(err => {
      console.warn('Failed to fetch latest session', err)
      // still open dialog without session id
      posDialogSessionId.value = null
      showPosDialog.value = true
    })
}

const posFillDo = (isVoided: boolean) => {
  const order_id = posDialogOrderId.value
  if (!order_id) return

  const payload: any = {
    order_id: order_id,
    date_time_closed: new Date().toISOString().slice(0, 19).replace('T', ' '),
    is_open: 0,
    is_voided: isVoided ? 1 : 0,
    session_id: posDialogSessionId.value,
  }

  router.post('/pos/fill-order', payload, {
    onSuccess: () => {
      toast.success(isVoided ? 'POS order marked as voided' : 'POS order marked as paid')
      showPosDialog.value = false
      posDialogOrderId.value = null
    },
    onError: (error: any) => {
      toast.error('POS fill failed')
      console.error('posFill error', error)
      showPosDialog.value = false
      posDialogOrderId.value = null
    }
  })
}

const openViewDialog = (order: any) => {
  viewDialogOrder.value = order
  showViewDialog.value = true
}

</script>

<template>

<DropdownMenu>
  <DropdownMenuTrigger as-child>
    <Button variant="ghost" class="h-8 w-8 p-0" aria-label="Open order actions menu">
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
    <DropdownMenuItem @click.prevent="openPosDialog(computedOrder.order_id as number | string)">
      Trigger POS Test Update
    </DropdownMenuItem>
    <DropdownMenuItem @click.prevent="openViewDialog(computedOrder)">
      View Order
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem @click="voidOrder">
      Void
    </DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>

<!-- View Order dialog -->
<Dialog v-model:open="showViewDialog">
  <DialogContent class="max-w-lg">
    <DialogHeader>
      <DialogTitle>Order Details</DialogTitle>
      <DialogDescription>View device order metadata and items</DialogDescription>
    </DialogHeader>

    <div class="mt-4 space-y-3 text-sm">
      <div><strong>Order #:</strong> {{ viewDialogOrder?.order_number }}</div>
      <div><strong>Status:</strong> {{ viewDialogOrder?.status }}</div>
      <div><strong>Guests:</strong> {{ viewDialogOrder?.guest_count }}</div>
      <div><strong>Created:</strong> {{ viewDialogOrder?.created_at }}</div>
      <div><strong>Subtotal:</strong> {{ viewDialogOrder?.subtotal ?? viewDialogOrder?.meta?.order_check?.subtotal ?? '-' }}</div>
      <div><strong>Total:</strong> {{ viewDialogOrder?.total ?? viewDialogOrder?.meta?.order_check?.total_amount ?? '-' }}</div>
    </div>

    <div class="mt-4">
      <p class="text-sm font-medium">Items</p>
      <div class="mt-2 divide-y">
        <div v-for="(it, idx) in (viewDialogOrder?.items || [])" :key="idx" class="py-2">
          <div class="flex justify-between">
            <div class="text-sm">{{ it.name }}</div>
            <div class="text-sm">Qty: {{ it.quantity }}</div>
          </div>
          <div class="text-xs text-muted-foreground">Price: {{ it.price ?? it.unit_price ?? '-' }} | Note: {{ it.note || '-' }}</div>
        </div>
      </div>
    </div>

    <DialogFooter class="mt-4">
      <Button variant="ghost" @click.prevent="showViewDialog = false">Close</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>

<!-- POS confirmation dialog -->
<Dialog v-model:open="showPosDialog">
  <DialogContent>
    <DialogHeader>
      <DialogTitle>Confirm POS Update</DialogTitle>
      <DialogDescription>Mark this POS order as paid or voided. This will update the POS orders table and trigger the payment/void logic.</DialogDescription>
    </DialogHeader>
    <div class="mt-4 space-y-2">
      <div class="text-sm">Order ID: <strong>{{ posDialogOrderId }}</strong></div>
      <div class="text-sm">
        <label class="block text-xs text-muted-foreground">Terminal Session ID (latest)</label>
        <input v-model="posDialogSessionId" type="number" class="mt-1 block w-full rounded border px-2 py-1" />
        <p v-if="!posDialogSessionId" class="text-xs text-red-600 mt-1">No active session found — please provide a session id to proceed.</p>
      </div>
    </div>
    <DialogFooter class="mt-4">
      <Button variant="ghost" @click.prevent="showPosDialog = false">Cancel</Button>
      <Button class="ml-2" :disabled="!posDialogSessionId" @click.prevent="posFillDo(false)">Mark as Paid</Button>
      <Button class="ml-2" variant="destructive" :disabled="!posDialogSessionId" @click.prevent="posFillDo(true)">Mark as Voided</Button>
    </DialogFooter>
  </DialogContent>
</Dialog>

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
