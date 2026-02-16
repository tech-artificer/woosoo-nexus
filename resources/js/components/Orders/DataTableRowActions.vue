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
import OrderDetails from '@/components/Orders/OrderDetails.vue'

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
const viewDialogLoading = ref(false);
const viewDialogError = ref<string | null>(null);
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
  showViewDialog.value = true
  viewDialogOrder.value = null
  viewDialogError.value = null
  viewDialogLoading.value = true

  const orderId = order?.id
  if (!orderId) {
    viewDialogError.value = 'Missing order id.'
    viewDialogLoading.value = false
    return
  }

  // Use fetch for JSON API endpoint
  fetch(`/orders/${orderId}`, {
    credentials: 'same-origin',
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json'
    }
  })
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }
      return response.json()
    })
    .then(data => {
      viewDialogOrder.value = data?.order ?? null
      if (!viewDialogOrder.value) {
        viewDialogError.value = 'Order data not found in response.'
      }
    })
    .catch(error => {
      console.warn('Failed to fetch order details', error)
      viewDialogError.value = `Failed to load order details: ${error.message}`
    })
    .finally(() => {
      viewDialogLoading.value = false
    })
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
      View Details
    </DropdownMenuItem>
    <DropdownMenuSeparator />
    <DropdownMenuItem @click="voidOrder">
      Void
    </DropdownMenuItem>
  </DropdownMenuContent>
</DropdownMenu>

<!-- View Order dialog -->
<Dialog v-model:open="showViewDialog">
  <DialogContent class="max-w-4xl">
    <DialogHeader>
      <DialogTitle>Order Details</DialogTitle>
      <DialogDescription>Full order details, items, and refill history</DialogDescription>
    </DialogHeader>

    <div class="mt-4">
      <OrderDetails :order="viewDialogOrder" :loading="viewDialogLoading" :error="viewDialogError" />
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
