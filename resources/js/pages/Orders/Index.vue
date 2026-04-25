<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { columns } from '@/components/Orders/columns';
import DataTable from '@/components/Orders/DataTable.vue'
import OrderDetailSheet from '@/components/Orders/OrderDetailSheet.vue'
import StatsCards from '@/components/Stats/StatsCards.vue'
import type { DeviceOrder, User} from '@/types/models';
import { toast } from 'vue-sonner';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs"
import { Badge } from '@/components/ui/badge'

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
const devices = props.devices ?? []
const tables = props.tables ?? []

// Keep a reactive local copy of orders so we can update in-place on Echo events
const localOrders = ref(Array.isArray(orders) ? [...orders] : [])
const localOrderHistory = ref(Array.isArray(orderHistory) ? [...orderHistory] : [])
const selectedOrder = ref<DeviceOrder | null>(null)
const isDetailOpen = ref(false)
// Track which order_id we are currently fetching to avoid race updates
const ongoingFetchOrderId = ref<number | string | null>(null)

// WebSocket connection state: 'connecting' | 'connected' | 'disconnected'
const echoStatus = ref<'connecting' | 'connected' | 'disconnected'>('connecting')

// Track which order IDs have active print animations to prevent duplicate animations
const animatedOrderIds = new Set<number>()

// Reactive stats always derived from live local arrays — never stale server snapshot
const liveStats = computed(() => [
  {
    title: 'Live Orders',
    value: localOrders.value.length,
    subtitle: 'Pending and in-progress',
    variant: 'primary' as const,
  },
  {
    title: 'Order History',
    value: localOrderHistory.value.length,
    subtitle: 'Completed / voided',
    variant: 'default' as const,
  },
])

// Play a short audio ping for new orders (no external dependency)
function playNewOrderPing() {
  try {
    const ctx = new (window.AudioContext || (window as any).webkitAudioContext)()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, ctx.currentTime)
    osc.frequency.setValueAtTime(660, ctx.currentTime + 0.12)
    gain.gain.setValueAtTime(0.35, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4)
    osc.start(ctx.currentTime)
    osc.stop(ctx.currentTime + 0.4)
  } catch {
    // AudioContext not supported — silent fallback
  }
}

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
  router.post('/orders/print', { order_id: orderId }, {
    preserveState: true,
    preserveScroll: true,
  })
}

const handleDetailComplete = () => {
  const orderId = selectedOrder.value?.order_id
  if (!orderId) return
  // Close sheet immediately — broadcast will move the row to history
  isDetailOpen.value = false
  router.post('/orders/complete', { order_id: orderId }, {
    preserveState: true,
    preserveScroll: true,
    onError: () => {
      // Re-open on failure so admin can retry
      isDetailOpen.value = true
      toast.error('Failed to complete order. Please try again.')
    },
  })
}

// DataTable handles all client-side column filtering


const handleOrderEvent = (event: DeviceOrder) => {
  const incoming = (event as any)?.order ? (event as any).order : event
  const incomingStatus = String((incoming as any).status ?? '').toLowerCase()
  const liveStatuses = ['confirmed', 'pending', 'in_progress', 'ready', 'served']
  const terminalStatuses = ['completed', 'voided', 'cancelled', 'archived']
  const incomingId = (incoming as any).id ?? (incoming as any).order_id
  if (!incomingId) return

  const orderFields = [
    'items', 'subtotal', 'tax', 'total', 'discount', 'guest_count', 'created_at', 'updated_at', 'is_printed', 'device', 'table', 'serviceRequests', 'order_id', 'order_number', 'status', 'id', 'branch_id', 'session_id', 'device_id', 'table_id', 'printed_at', 'printed_by'
  ]

  const mergeList = (list: any[]) => {
    const idx = list.findIndex(o => (o.id ?? o.order_id) === incomingId || o.order_number === incoming.order_number)
    if (idx === -1) return [Object.assign({}, incoming), ...list]
    const merged = { ...list[idx] }
    orderFields.forEach(f => { merged[f] = incoming[f] })
    const next = [...list]
    next[idx] = merged
    return next
  }

  if (selectedOrder.value && ((selectedOrder.value.id ?? selectedOrder.value.order_id) === incomingId || selectedOrder.value.order_number === incoming.order_number)) {
    orderFields.forEach(field => {
      (selectedOrder.value as any)[field] = incoming[field];
    });
  }

  const isRefill = Array.isArray(incoming.items) && incoming.items.some((it: any) => {
    return it.is_refill || (it.name && String(it.name).toLowerCase().includes('refill')) || it.type === 'refill'
  })

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

  try {
    if (terminalStatuses.includes(incomingStatus)) {
      const liveIdx = localOrders.value.findIndex(o => (o.id ?? o.order_id) === incomingId || o.order_number === incoming.order_number)
      if (liveIdx !== -1) {
        localOrders.value = localOrders.value.filter((_, i) => i !== liveIdx)
      }
      localOrderHistory.value = mergeList(localOrderHistory.value)
      return
    }

    if (liveStatuses.includes(incomingStatus)) {
      const existingIdx = localOrders.value.findIndex(o => (o.id ?? o.order_id) === incomingId || o.order_number === incoming.order_number)
      const wasNotPrinted = existingIdx !== -1 ? !localOrders.value[existingIdx].is_printed : false
      localOrders.value = mergeList(localOrders.value)
      const justPrinted = wasNotPrinted && !!incoming.is_printed
      if (justPrinted && incomingId && !animatedOrderIds.has(incomingId)) {
        animatedOrderIds.add(incomingId)
        const rowElement = document.querySelector(`[data-order-id="${incomingId}"]`)
        if (rowElement) {
          rowElement.classList.add('print-highlight')
          setTimeout(() => {
            rowElement.classList.remove('print-highlight')
            animatedOrderIds.delete(incomingId)
          }, 5000)
        }
      }
    }
  } catch (err) {
    console.error('Failed to update localOrders in-place', err)
    setTimeout(() => {
      router.reload({ only: ['orders', 'orderHistory'] });
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
      handleOrderEvent(e);
      // Notify staff a new order has arrived
      const order = (e as any)?.order ?? e
      const label = order?.order_number ? `Order #${order.order_number}` : 'New order'
      const table = order?.table?.name ? ` — Table ${order.table.name}` : ''
      toast.success(`${label} placed${table}`, {
        description: `${order?.guest_count ?? ''} guest(s)`.trim(),
        duration: 8000,
      })
      playNewOrderPing()
    })
    .listen('.order.completed', (e: DeviceOrder) => {
      handleOrderEvent(e);
      const order = (e as any)?.order ?? e
      toast.info(`Order #${order?.order_number ?? ''} completed`, { duration: 4000 })
    })
    .listen('.order.cancelled', (e: DeviceOrder) => {
      handleOrderEvent(e);
      const order = (e as any)?.order ?? e
      toast.warning(`Order #${order?.order_number ?? ''} cancelled`, { duration: 5000 })
    })
    .listen('.order.voided', (e: DeviceOrder) => {
      handleOrderEvent(e);
      const order = (e as any)?.order ?? e
      toast.warning(`Order #${order?.order_number ?? ''} voided`, { duration: 5000 })
    })
    .listen('.order.updated', (e: DeviceOrder) => {
      handleOrderEvent(e);
    })
    .listen('.order.printed', (e: DeviceOrder) => {
      handleOrderEvent(e);
    })
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.orders channel:', error);
      echoStatus.value = 'disconnected'
    });

  // Track Pusher/Reverb connection state for the status pill
  try {
    const pusher = (window.Echo as any).connector?.pusher
    if (pusher) {
      pusher.connection.bind('connected', () => { echoStatus.value = 'connected' })
      pusher.connection.bind('disconnected', () => { echoStatus.value = 'disconnected' })
      pusher.connection.bind('connecting', () => { echoStatus.value = 'connecting' })
      pusher.connection.bind('unavailable', () => { echoStatus.value = 'disconnected' })
      // Reflect current state immediately
      const state = pusher.connection.state
      if (state === 'connected') echoStatus.value = 'connected'
      else if (state === 'disconnected' || state === 'unavailable' || state === 'failed') echoStatus.value = 'disconnected'
    }
  } catch {
    // Pusher connector not available (e.g. Reverb native driver) — leave as 'connecting'
  }

  const serviceRequestsChannel = window.Echo.channel('admin.service-requests');
  serviceRequestsChannel
    .listen('.service-request.notification', () => {})
    .error((error: unknown) => {
      console.error('[Echo] Error connecting to admin.service-requests channel:', error);
    });
  
  const printChannel = window.Echo.channel('admin.print');
  printChannel
    .listen('.order.printed', () => {})
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
        ;(window.Echo as any).leave('admin.orders')
        ;(window.Echo as any).leave('admin.service-requests')
        ;(window.Echo as any).leave('admin.print')
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
        <!-- WebSocket connection status pill -->
        <div class="flex items-center justify-end">
          <span
            :class="[
              'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
              echoStatus === 'connected'    && 'bg-emerald-50 text-emerald-700',
              echoStatus === 'connecting'   && 'bg-yellow-50 text-yellow-700',
              echoStatus === 'disconnected' && 'bg-rose-50 text-rose-700',
            ]"
          >
            <span
              :class="[
                'h-1.5 w-1.5 rounded-full',
                echoStatus === 'connected'    && 'bg-emerald-500',
                echoStatus === 'connecting'   && 'bg-yellow-500 animate-pulse',
                echoStatus === 'disconnected' && 'bg-rose-500',
              ]"
            />
            <span v-if="echoStatus === 'connected'">Live</span>
            <span v-else-if="echoStatus === 'connecting'">Connecting…</span>
            <span v-else>Disconnected — refresh to reconnect</span>
          </span>
        </div>

        <Tabs default-value="live_orders" class="space-y-4">
                <TabsList class="inline-flex h-11 w-auto p-1">
                    <TabsTrigger value="live_orders">
                        <span class="flex items-center gap-2">
                            Live Orders
                            <Badge
                              v-if="localOrders.length > 0"
                              class="h-5 min-w-5 rounded-full px-1.5 text-xs tabular-nums bg-blue-600 text-white"
                            >
                              {{ localOrders.length }}
                            </Badge>
                        </span>
                    </TabsTrigger>
                    <TabsTrigger value="order_history">
                        <span class="flex items-center gap-2">
                            Order History
                            <Badge
                              v-if="localOrderHistory.length > 0"
                              variant="secondary"
                              class="h-5 min-w-5 rounded-full px-1.5 text-xs tabular-nums"
                            >
                              {{ localOrderHistory.length }}
                            </Badge>
                        </span>
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="live_orders" class="pt-3 px-1 sm:px-2 space-y-4">
                  <!-- Filters have been moved into the Orders DataTable toolbar -->
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <!-- Always reactive — derived from localOrders/localOrderHistory, never from static server prop -->
                    <StatsCards :cards="liveStats" />
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
