<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { columns } from '@/components/Orders/columns';
import DataTable from '@/components/Orders/DataTable.vue'
import OrderDetailSheet from '@/components/Orders/OrderDetailSheet.vue'
import StatsCards from '@/components/Stats/StatsCards.vue'
import type { DeviceOrder, ServiceRequest, User} from '@/types/models';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs"

interface OrdersPageProps {
  title: string
  description: string
  orders: DeviceOrder[]
  orderHistory: DeviceOrder[]
  stats?: Record<string, any>
  devices?: Record<string, any>[]
  tables?: Record<string, any>[]
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Orders',
        href: route('orders.index'),
    },
];

const page = usePage();
const user = (page.props.auth as any)?.user as User;

const props = defineProps<OrdersPageProps>()

const orders = props.orders ?? []
const orderHistory = props.orderHistory ?? []
const stats = props.stats ?? null
const devices = props.devices ?? []
const tables = props.tables ?? []

// Keep a reactive local copy of orders so we can update in-place on Echo events
const localOrders = ref(Array.isArray(orders) ? [...orders] : [])
const localOrderHistory = ref(Array.isArray(orderHistory) ? [...orderHistory] : [])
const selectedOrder = ref<DeviceOrder | null>(null)
const isDetailOpen = ref(false)
// Track which order_id we are currently fetching to avoid race updates
const ongoingFetchOrderId = ref<number | string | null>(null)

// Track which order IDs have active print animations to prevent duplicate animations
const animatedOrderIds = new Set<number>()

const openOrderDetail = (order: DeviceOrder) => {
  try {
    // Immediately open the sheet with the row projection to keep UI non-blocking
    selectedOrder.value = order
    ;(selectedOrder.value as any).__is_partial = true
    isDetailOpen.value = true

    // If the row already contains an items array, no background fetch needed
    const rowItems = (order as any).items
    if (Array.isArray(rowItems) && rowItems.length > 0) {
      ;(selectedOrder.value as any).__is_partial = false
      return
    }

    // Otherwise fetch canonical full order payload in background and update when ready
    const orderId = String((order as any).order_id || (order as any).id)
    if (!orderId) return

    ongoingFetchOrderId.value = orderId
    fetch(`/device-order/by-order-id/${orderId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(res => {
        if (!res.ok) throw new Error(String(res.status))
        return res.json()
      })
      .then(full => {
        // Only update if the same order is still the one being viewed
        if (ongoingFetchOrderId.value !== orderId) return
        selectedOrder.value = full
        ;(selectedOrder.value as any).__is_partial = false
        ongoingFetchOrderId.value = null
      })
      .catch(err => {
        console.warn('Background fetch for full order failed', err)
        ongoingFetchOrderId.value = null
      })
  } catch (err) {
    console.error('openOrderDetail error', err)
    selectedOrder.value = order
    isDetailOpen.value = true
  }
}

const handleDetailPrint = () => {
  const orderId = selectedOrder.value?.order_id
  if (!orderId) return
  router.post('/orders/print', { order_id: orderId })
}

const handleDetailComplete = () => {
  const orderId = selectedOrder.value?.order_id
  if (!orderId) return
  router.post('/orders/complete', { order_id: orderId })
}

// DataTable handles all client-side column filtering


const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
  // Extract order from event (some events wrap in { order: {...} })
  const incoming = event.order ? event.order : event

  // Only process orders with confirmed or active status for live orders
  const liveStatuses = ['confirmed', 'pending', 'in_progress', 'ready', 'served']
  const terminalStatuses = ['completed', 'voided', 'cancelled', 'archived']
  const incomingStatus = String((incoming as any).status).toLowerCase()

  // Heuristic: detect "refill" in items. Adjust to your domain's true refill marker.
  const isRefill = Array.isArray(incoming.items) && incoming.items.some((it: any) => {
    return it.is_refill || (it.name && String(it.name).toLowerCase().includes('refill')) || it.type === 'refill'
  })

  // Patch: Always update all order fields for real-time sync
  const orderFields = [
    'items', 'subtotal', 'tax', 'total', 'discount', 'guest_count', 'created_at', 'updated_at', 'is_printed', 'device', 'table', 'serviceRequests', 'order_id', 'order_number', 'status', 'id', 'branch_id', 'session_id', 'device_id', 'table_id'
  ];

  // Update selectedOrder if open
  if (selectedOrder.value && (selectedOrder.value.id === incoming.id || selectedOrder.value.order_number === incoming.order_number)) {
    orderFields.forEach(field => {
      selectedOrder.value[field] = incoming[field];
    });
  }

  // Refill logic (unchanged)
  if (isRefill) {
    try {
      if (window.Notification) {
        if (Notification.permission === 'granted') {
          new Notification('Order Refill', { body: `Order ${incoming.order_number} contains a refill.` })
        } else if (Notification.permission !== 'denied') {
          Notification.requestPermission().then(p => {
            if (p === 'granted') new Notification('Order Refill', { body: `Order ${incoming.order_number} contains a refill.` })
          })
        }
      }
    } catch (e) {
      console.warn('Notification error', e)
    }
    try {
      window.dispatchEvent(new CustomEvent('order.refill', { detail: incoming }))
    } catch (e) {
      console.warn('Failed to dispatch order.refill event', e)
    }
    try {
      const refillItems = Array.isArray(incoming.items) ? incoming.items.filter((it: any) => it.is_refill || (it.name && String(it.name).toLowerCase().includes('refill')) || it.type === 'refill').map((it: any) => ({ name: it.name, quantity: it.quantity })) : []
      if (refillItems.length) {
        fetch(`/api/order/${incoming.order_id}/print-refill`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ items: refillItems }),
        }).then(res => {
          if (!res.ok) console.warn('print-refill request failed', res.status)
        }).catch(err => console.warn('print-refill request error', err))
      }
    } catch (err) {
      console.warn('Failed to call print-refill API', err)
    }
  }

  // Update localOrders / localOrderHistory - use array reassignment for proper reactivity
  try {
    const idx = localOrders.value.findIndex(o => o.id === incoming.id || o.order_number === incoming.order_number)
    // Patch: Always update all fields
    if (idx !== -1) {
      orderFields.forEach(field => {
        localOrders.value[idx][field] = incoming[field];
      });
    }

    // If status indicates completion/void, remove from live and add to history
    if (terminalStatuses.includes(incomingStatus)) {
      if (idx !== -1) {
        const removed = localOrders.value[idx]
        const merged = { ...removed, ...incoming }
        localOrders.value = localOrders.value.filter((_, i) => i !== idx)
        const histIdx = localOrderHistory.value.findIndex(o => o.id === incoming.id || o.order_number === incoming.order_number)
        if (histIdx === -1) {
          localOrderHistory.value = [merged, ...localOrderHistory.value]
        } else {
          orderFields.forEach(field => {
            localOrderHistory.value[histIdx][field] = merged[field];
          });
        }
        return
      } else {
        const histIdx = localOrderHistory.value.findIndex(o => o.id === incoming.id || o.order_number === incoming.order_number)
        if (histIdx === -1) {
          localOrderHistory.value = [incoming, ...localOrderHistory.value]
        } else {
          orderFields.forEach(field => {
            localOrderHistory.value[histIdx][field] = incoming[field];
          });
        }
        return
      }
    }

    // For live statuses, update or add to live orders
    if (liveStatuses.includes(incomingStatus)) {
      if (idx !== -1) {
        const wasNotPrinted = !localOrders.value[idx].is_printed
        const isNowPrinted = incoming.is_printed
        const justPrinted = wasNotPrinted && isNowPrinted
        // Patch: Always update all fields
        orderFields.forEach(field => {
          localOrders.value[idx][field] = incoming[field];
        });
        if (justPrinted && incoming.id && !animatedOrderIds.has(incoming.id)) {
          animatedOrderIds.add(incoming.id)
          const rowElement = document.querySelector(`[data-order-id="${incoming.id}"]`)
          if (rowElement) {
            rowElement.classList.add('print-highlight')
            setTimeout(() => {
              rowElement.classList.remove('print-highlight')
              animatedOrderIds.delete(incoming.id)
            }, 5000)
          }
        }
      } else {
        localOrders.value = [incoming, ...localOrders.value]
      }
    }
  } catch (err) {
    console.error('Failed to update localOrders in-place', err)
    setTimeout(() => {
      router.visit(route('orders.index'));
    }, 50);
    return
  }
};

// Handler for local refill event so we can register/unregister in lifecycle hooks
let orderRefillHandler: ((ev: any) => void) | null = null

onMounted(() => {
  if (!window.Echo) {
    console.error('Orders/Index.vue: window.Echo is not available.');
    return;
  }

  if (!user || !user.is_admin) {
    console.warn('[Orders/Index.vue] Skipping channel subscription - user not admin or not available');
    return;
  }

  const adminOrdersChannel = window.Echo.channel('admin.orders');
  adminOrdersChannel
    .listen('.order.created', (e: DeviceOrder) => {
      handleOrderEvent(e, false);
    })
    .listen('.order.completed', (e: DeviceOrder) => {
      handleOrderEvent(e, true);
    })
    .listen('.order.voided', (e: DeviceOrder) => {
      handleOrderEvent(e, true);
    })
    .listen('.order.updated', (e: DeviceOrder) => {
      handleOrderEvent(e, true);
    })
    .listen('.order.printed', (e: DeviceOrder) => {
      handleOrderEvent(e, true);
    })
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.orders channel:', error);
    });

  const serviceRequestsChannel = window.Echo.channel('admin.service-requests');
  serviceRequestsChannel
    .listen('.service-request.notification', (_e: ServiceRequest) => {})
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.service-requests channel:', error);
    });
  
  const printChannel = window.Echo.channel('admin.print');
  printChannel
    .listen('.order.printed', (_e: DeviceOrder) => {})
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.print channel:', error);
    });

  // Register local 'order.refill' handler to mark rows visually
  orderRefillHandler = (ev: any) => {
    try {
      const payload = (ev && ev.detail) ? ev.detail : ev
      const idx = localOrders.value.findIndex(o => o.id === payload.id || o.order_number === payload.order_number)
      if (idx !== -1) {
        localOrders.value[idx] = Object.assign({}, localOrders.value[idx], payload, { __is_refill: true })
      } else {
        // insert new with refill flag
        localOrders.value.unshift(Object.assign({}, payload, { __is_refill: true }))
      }

      // clear refill highlight after 20 seconds
      setTimeout(() => {
        const i = localOrders.value.findIndex(o => o.id === payload.id || o.order_number === payload.order_number)
        if (i !== -1 && localOrders.value[i].__is_refill) {
          localOrders.value[i].__is_refill = false
        }
      }, 20000)
    } catch (e) {
      console.warn('order.refill handler failed', e)
    }
  }

  window.addEventListener('order.refill', orderRefillHandler)
});

onUnmounted(() => {
  if (window.Echo) {
    try {
      // gracefully leave channels we joined
      if (typeof (window.Echo as any).leave === 'function') {
        (window.Echo as any).leave('admin.orders')
        (window.Echo as any).leave('admin.service-requests')
        (window.Echo as any).leave('admin.print')
      }
    } catch (e) {
      console.warn('Error leaving Echo channels', e)
    }
  }

  if (orderRefillHandler) {
    window.removeEventListener('order.refill', orderRefillHandler)
    orderRefillHandler = null
  }
});

</script>

<style scoped>
@keyframes print-highlight {
  0% {
    border-left: 4px solid rgb(34, 197, 94);
    background-color: rgba(34, 197, 94, 0.05);
  }
  100% {
    border-left: 4px solid transparent;
    background-color: transparent;
  }
}

.print-highlight {
  animation: print-highlight 5s ease-out;
}
</style>

<template>
    <Head :title="title" :description="description" />
   
    <AppLayout :breadcrumbs="breadcrumbs">   
      <div class="space-y-4 px-1 sm:px-2">
        <Tabs default-value="live_orders" class="space-y-4">
                <TabsList class="grid h-11 w-full grid-cols-2 p-1">
                    <TabsTrigger value="live_orders">
                        Live Orders
                    </TabsTrigger>
                    <TabsTrigger value="order_history">
                        Order History
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="live_orders" class="pt-3 px-1 sm:px-2 space-y-4">
                  <!-- Filters have been moved into the Orders DataTable toolbar -->
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <StatsCards :cards="(stats ?? [
                      { title: 'Live Orders', value: localOrders.length ?? 0, subtitle: 'Pending and in-progress', variant: 'primary' },
                      { title: 'Order History', value: localOrderHistory.length ?? 0, subtitle: 'Completed/voided', variant: 'default' },
                    ])" />
                  </div>

                  <div class="w-full overflow-x-auto">
                    <DataTable
                      :data="localOrders"
                      :columns="columns"
                      :devices="devices"
                      :tables="tables"
                      @row-click="openOrderDetail"
                    />
                  </div>
                </TabsContent>
                <TabsContent value="order_history" class="pt-3 px-1 sm:px-2 space-y-4">
                  <div class="w-full overflow-x-auto">
                    <DataTable
                      :data="localOrderHistory"
                      :columns="columns"
                      :devices="devices"
                      :tables="tables"
                      @row-click="openOrderDetail"
                    />
                  </div>
                </TabsContent>
            </Tabs>

            <OrderDetailSheet
              v-model:open="isDetailOpen"
              :order="selectedOrder"
              @print="handleDetailPrint"
              @complete="handleDetailComplete"
            />
        </div>
    </AppLayout>
</template>
