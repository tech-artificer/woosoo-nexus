<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { columns } from '@/components/Orders/columns';
import DataTable from '@/components/Orders/DataTable.vue'
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

// Track which order IDs have active print animations to prevent duplicate animations
const animatedOrderIds = new Set<number>()

// Test function to verify reactivity is working
const testAddOrder = () => {
  const testOrder = {
    id: Date.now(),
    order_id: Date.now(),
    order_number: `TEST-${Date.now()}`,
    status: 'confirmed',
    total: 100,
    created_at: new Date().toISOString(),
    device: { id: 1, name: 'Test Device' },
    table: { id: 1, name: 'Test Table' },
  }
  console.log('Adding test order:', testOrder)
  localOrders.value = [testOrder, ...localOrders.value]
  console.log('localOrders now has', localOrders.value.length, 'items')
}

// DataTable handles all client-side column filtering

const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
  console.log('Orders/Index.vue - Order event received:', event, 'Is update:', isUpdate);

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

  if (isRefill) {
    console.log('Refill detected for order:', incoming.order_number)
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
      // fall back to console
      console.log('Notification error', e)
    }
    // Dispatch a local custom event so other parts of the app can react without a full reload
    try {
      window.dispatchEvent(new CustomEvent('order.refill', { detail: incoming }))
    } catch (e) {
      console.log('Failed to dispatch order.refill event', e)
    }

    // Send only refill items to the server print-refill endpoint so printer prints only those
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

    // If status indicates completion/void, remove from live and add to history
    if (terminalStatuses.includes(incomingStatus)) {
      if (idx !== -1) {
        const removed = localOrders.value[idx]
        const merged = { ...removed, ...incoming }
        // Remove from live orders (create new array)
        localOrders.value = localOrders.value.filter((_, i) => i !== idx)
        // Add to history
        const histIdx = localOrderHistory.value.findIndex(o => o.id === incoming.id || o.order_number === incoming.order_number)
        if (histIdx === -1) {
          localOrderHistory.value = [merged, ...localOrderHistory.value]
        } else {
          localOrderHistory.value = localOrderHistory.value.map((o, i) => 
            i === histIdx ? { ...o, ...merged } : o
          )
        }
        console.log('Order moved to history:', merged.order_number, 'Live orders:', localOrders.value.length)
        return
      } else {
        // not in live table - ensure in history
        const histIdx = localOrderHistory.value.findIndex(o => o.id === incoming.id || o.order_number === incoming.order_number)
        if (histIdx === -1) {
          localOrderHistory.value = [incoming, ...localOrderHistory.value]
        } else {
          localOrderHistory.value = localOrderHistory.value.map((o, i) => 
            i === histIdx ? { ...o, ...incoming } : o
          )
        }
        return
      }
    }

    // For live statuses, update or add to live orders
    if (liveStatuses.includes(incomingStatus)) {
      if (idx !== -1) {
        // Check if order was just printed (is_printed changed from false to true)
        const wasNotPrinted = !localOrders.value[idx].is_printed
        const isNowPrinted = incoming.is_printed
        const justPrinted = wasNotPrinted && isNowPrinted

        // Update existing order (create new array)
        localOrders.value = localOrders.value.map((o, i) => 
          i === idx ? { ...o, ...incoming } : o
        )
        console.log('Order updated:', incoming.order_number)

        // Trigger animation if order was just printed
        if (justPrinted && incoming.id && !animatedOrderIds.has(incoming.id)) {
          animatedOrderIds.add(incoming.id)
          const rowElement = document.querySelector(`[data-order-id="${incoming.id}"]`)
          if (rowElement) {
            rowElement.classList.add('print-highlight')
            setTimeout(() => {
              rowElement.classList.remove('print-highlight')
              animatedOrderIds.delete(incoming.id)
            }, 5000) // 5 second animation
          }
        }
      } else {
        // New order: add to top (create new array)
        localOrders.value = [incoming, ...localOrders.value]
        console.log('New order added:', incoming.order_number, 'Live orders:', localOrders.value.length)
      }
    }
  } catch (err) {
    console.error('Failed to update localOrders in-place', err)
    // fallback: reload page to ensure consistency
    setTimeout(() => {
      router.visit(route('orders.index'));
    }, 50);
    return
  }
};

// Handler placeholder so we can register/unregister in lifecycle hooks
let orderRefillHandler: ((ev: any) => void) | null = null

// Dev helper: emit a sample refill event (admin-only button invokes this)
// function emitTestRefill() {
//   const sample = (localOrders && localOrders.length) ? Object.assign({}, localOrders[0]) : null
//   const testOrder = sample ?? {
//     id: Date.now(),
//     order_number: `TEST-${Date.now()}`,
//     status: 'in_progress',
//     items: [{ id: 1, name: 'Refill Bottle', quantity: 1, is_refill: true }],
//     created_at: new Date().toISOString(),
//   }
//   // Call the same handler that would be used by Echo
//   try {
//     handleOrderEvent(testOrder, false)
//     // also dispatch the global refill event so other listeners can react
//     window.dispatchEvent(new CustomEvent('order.refill', { detail: testOrder }))
//   } catch (e) {
//     console.error('emitTestRefill failed', e)
//   }
// }

onMounted(() => {
  console.log('[Orders/Index.vue] Component mounted');
  console.log('[Orders/Index.vue] window.Echo available:', !!window.Echo);
  console.log('[Orders/Index.vue] user:', user);
  console.log('[Orders/Index.vue] user.is_admin:', user?.is_admin);

  if (!window.Echo) {
    console.error('Orders/Index.vue: window.Echo is not available.');
    return;
  }

  if (!user || !user.is_admin) {
    console.warn('[Orders/Index.vue] Skipping channel subscription - user not admin or not available');
    return;
  }

  console.log('Orders/Index.vue mounted. Joining channels.');

  // Log all subscribed channels for debugging
  const subscribedChannels = ['admin.orders', 'admin.service-requests', 'admin.print'];
  console.log('[Echo] Subscribing to channels:', subscribedChannels);
  console.log('[Echo] Connection state:', window.Echo.connector?.pusher?.connection?.state ?? 'unknown');
  console.log('[Echo] Socket ID:', window.Echo.socketId?.() ?? 'not available');

  const adminOrdersChannel = window.Echo.channel('admin.orders');
  console.log('[Echo] Joined admin.orders channel:', adminOrdersChannel);
  adminOrdersChannel
    .listen('.order.created', (e: DeviceOrder) => {
      console.log('[Echo] admin.orders - order.created event:', e);
      handleOrderEvent(e, false);
    })
    .listen('.order.completed', (e: DeviceOrder) => {
      console.log('[Echo] admin.orders - order.completed event:', e);
      handleOrderEvent(e, true);
    })
    .listen('.order.voided', (e: DeviceOrder) => {
      console.log('[Echo] admin.orders - order.voided event:', e);
      handleOrderEvent(e, true);
    })
    .listen('.order.updated', (e: DeviceOrder) => {
      console.log('[Echo] admin.orders - order.updated event:', e);
      handleOrderEvent(e, true);
    })
    .listen('.order.printed', (e: DeviceOrder) => {
      console.log('[Echo] admin.orders - order.printed event:', e);
      handleOrderEvent(e, true);
    })
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.orders channel:', error);
    });

  const serviceRequestsChannel = window.Echo.channel('admin.service-requests');
  console.log('[Echo] Joined admin.service-requests channel:', serviceRequestsChannel);
  serviceRequestsChannel
    .listen('.service-request.notification', (e: ServiceRequest) => {
      console.log('[Echo] admin.service-requests - service-request.notification event:', e);
    })
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.service-requests channel:', error);
    });
  
  const printChannel = window.Echo.channel('admin.print');
  console.log('[Echo] Joined admin.print channel:', printChannel);
  printChannel
    .listen('.order.printed', (e: DeviceOrder) => {
      console.log('[Echo] admin.print - order.printed event:', e);
    })
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
    console.log('Display.vue unmounted. Leaving channels.');
    try {
      // gracefully leave channels we joined
      if ((window.Echo as any).leave) {
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
      <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
        <Tabs default-value="live_orders" class="">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="live_orders">
                        Live Orders
                    </TabsTrigger>
                    <TabsTrigger value="order_history">
                        Order History
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="live_orders" class="p-2">
                  <!-- Filters have been moved into the Orders DataTable toolbar -->
                  <div class="flex items-center justify-between mb-3">
                    <StatsCards :cards="(stats ?? [
                      { title: 'Live Orders', value: localOrders.length ?? 0, subtitle: 'Pending and in-progress', variant: 'primary' },
                      { title: 'Order History', value: localOrderHistory.length ?? 0, subtitle: 'Completed/voided', variant: 'default' },
                    ])" />

                    <!-- Debug button to test reactivity -->
                    <button 
                      v-if="user?.is_admin" 
                      @click="testAddOrder" 
                      class="px-3 py-2 rounded bg-blue-500 text-white text-sm ml-4"
                    >
                      Test Add Order
                    </button>
                  </div>

                  <DataTable :data="localOrders" :columns="columns" :devices="devices" :tables="tables" />
                </TabsContent>
                <TabsContent value="order_history" class="p-2">
                  <DataTable :data="localOrderHistory" :columns="columns" :devices="devices" :tables="tables" />  
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
