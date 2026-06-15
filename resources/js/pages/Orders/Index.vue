<script setup lang="ts">
import { onMounted, onUnmounted, ref, computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { columns } from '@/components/Orders/columns';
import DataTable from '@/components/Orders/DataTable.vue';
import OrderDetailSheet from '@/components/Orders/OrderDetailSheet.vue';
import OrderStatusBadge from '@/components/Orders/OrderStatusBadge.vue';
import type { DeviceOrder, User} from '@/types/models';
import { formatCurrency } from '@/lib/utils';
import { toast } from 'vue-sonner';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { RefreshCw } from 'lucide-vue-next'

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

// When Echo reconnects after a disconnection, reload orders to catch any missed
// during the gap. When disconnected for too long, poll as a fallback.
let disconnectPollTimer: ReturnType<typeof setInterval> | null = null

function refreshLocalOrdersFromPage() {
  const updatedOrders = (page.props as any).orders ?? []
  const updatedHistory = (page.props as any).orderHistory ?? []
  localOrders.value = Array.isArray(updatedOrders) ? [...updatedOrders] : []
  localOrderHistory.value = Array.isArray(updatedHistory) ? [...updatedHistory] : []
}

function reloadOrdersFromServer() {
  router.reload({
    only: ['orders', 'orderHistory'],
    onSuccess: refreshLocalOrdersFromPage,
  })
}

watch(echoStatus, (newStatus, oldStatus) => {
  if (newStatus === 'connected' && oldStatus === 'disconnected') {
    // Only reload when recovering from a real disconnection, not on initial connect.
    reloadOrdersFromServer()
    if (disconnectPollTimer !== null) {
      clearInterval(disconnectPollTimer)
      disconnectPollTimer = null
    }
  } else if (newStatus === 'disconnected') {
    // Fallback: while still disconnected, reload every 30 s so the board doesn't
    // drift stale during longer outages (not just a single one-shot reload).
    if (disconnectPollTimer !== null) clearInterval(disconnectPollTimer)
    disconnectPollTimer = setInterval(reloadOrdersFromServer, 30_000)
  }
})

// Track which order IDs have active print animations to prevent duplicate animations
const animatedOrderIds = new Set<number>()

const KANBAN_COLUMNS = [
  { key: 'confirmed', label: 'CONFIRMED', statuses: ['confirmed', 'pending', 'in_progress', 'ready', 'served'] },
  { key: 'completed', label: 'COMPLETED', statuses: ['completed'] },
  { key: 'voided', label: 'VOIDED', statuses: ['voided'] },
  { key: 'cancelled', label: 'CANCELLED', statuses: ['cancelled'] },
] as const

function orderStatusKey(order: DeviceOrder): string {
  return String(order.status ?? '').toLowerCase()
}

function ordersInColumn(statuses: readonly string[]): DeviceOrder[] {
  return filteredLocalOrders.value.filter((o) => statuses.includes(orderStatusKey(o)))
}

const kanbanStatusFilter = ref<string[]>([])
const kanbanTableFilter = ref<string>('all')
const kanbanTimeRange = ref<'all' | 'today' | 'hour'>('all')

const kanbanTableOptions = computed(() => {
  const names = new Set<string>()
  localOrders.value.forEach((o) => {
    const name = o.table?.name
    if (name) names.add(name)
  })
  return Array.from(names).sort()
})

const filteredLocalOrders = computed(() => {
  let list = localOrders.value

  if (kanbanStatusFilter.value.length > 0) {
    list = list.filter((o) => kanbanStatusFilter.value.includes(orderStatusKey(o)))
  }

  if (kanbanTableFilter.value !== 'all') {
    list = list.filter((o) => o.table?.name === kanbanTableFilter.value)
  }

  if (kanbanTimeRange.value === 'today') {
    const today = new Date().toDateString()
    list = list.filter((o) => o.created_at && new Date(o.created_at).toDateString() === today)
  } else if (kanbanTimeRange.value === 'hour') {
    const cutoff = Date.now() - 3_600_000
    list = list.filter((o) => o.created_at && new Date(o.created_at).getTime() >= cutoff)
  }

  return list
})

const kanbanStatusOptions = [
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'In Progress', value: 'in_progress' },
  { label: 'Ready', value: 'ready' },
  { label: 'Served', value: 'served' },
]

function toggleKanbanStatus(value: string) {
  const idx = kanbanStatusFilter.value.indexOf(value)
  if (idx === -1) {
    kanbanStatusFilter.value = [...kanbanStatusFilter.value, value]
  } else {
    kanbanStatusFilter.value = kanbanStatusFilter.value.filter((s) => s !== value)
  }
}

function handleKanbanRefresh() {
  reloadOrdersFromServer()
  toast.success('Orders refreshed')
}

const kanbanColumns = computed(() =>
  KANBAN_COLUMNS.map((col) => {
    const orders = ordersInColumn(col.statuses)
    return { ...col, orders, count: orders.length }
  }),
)

const dispatchSummary = computed(() => {
  const confirmed = ordersInColumn(KANBAN_COLUMNS[0].statuses).length
  const completed = ordersInColumn(KANBAN_COLUMNS[1].statuses).length
  const voidedCancelled =
    ordersInColumn(KANBAN_COLUMNS[2].statuses).length +
    ordersInColumn(KANBAN_COLUMNS[3].statuses).length
  return { confirmed, completed, voidedCancelled }
})

function formatElapsed(createdAt?: string | null): string {
  if (!createdAt) return '—'
  const diffMs = Date.now() - new Date(createdAt).getTime()
  if (!Number.isFinite(diffMs) || diffMs < 0) return '—'
  const mins = Math.floor(diffMs / 60000)
  if (mins < 60) return `${mins}m`
  const hrs = Math.floor(mins / 60)
  const rem = mins % 60
  return rem > 0 ? `${hrs}h ${rem}m` : `${hrs}h`
}

function orderTotal(order: DeviceOrder): number {
  const raw = order.total ?? (order as any).total_amount ?? (order.meta as any)?.order_check?.total_amount
  return typeof raw === 'number' ? raw : Number(raw || 0)
}

function orderItemsPreview(order: DeviceOrder): { name: string; quantity: number }[] {
  const items = Array.isArray(order.items) ? order.items : []
  return items.slice(0, 2).map((it: any) => ({
    name: it.name || it.menu?.name || 'Item',
    quantity: it.quantity ?? it.qty ?? 1,
  }))
}

function packageLabel(order: DeviceOrder): string {
  return order.name || (order.meta as any)?.package?.name || '—'
}

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
  router.post(route('orders.print'), { order_id: orderId }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => toast.success('Order Sent to Printer'),
    onError: () => toast.error('Failed to send order to printer.'),
  })
}

const handleDetailComplete = () => {
  const orderId = selectedOrder.value?.order_id
  if (!orderId) return
  isDetailOpen.value = false
  router.post(route('orders.complete'), { order_id: orderId }, {
    preserveState: true,
    preserveScroll: true,
    onError: () => {
      isDetailOpen.value = true
      toast.error('Failed to complete order. Please try again.')
    },
  })
}

const handleDetailVoid = () => {
  const id = selectedOrder.value?.id
  if (!id) return
  isDetailOpen.value = false
  router.delete(route('orders.destroy', { id }), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => toast.success('Order voided'),
    onError: () => {
      isDetailOpen.value = true
      toast.error('Failed to void order. Please try again.')
    },
  })
}

const handleDetailCancel = () => {
  const id = selectedOrder.value?.id
  if (!id) return
  isDetailOpen.value = false
  router.post(route('orders.update-status', { id }), { status: 'cancelled' }, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => toast.success('Order cancelled'),
    onError: () => {
      isDetailOpen.value = true
      toast.error('Failed to cancel order. Please try again.')
    },
  })
}

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
  if (disconnectPollTimer !== null) {
    clearInterval(disconnectPollTimer)
    disconnectPollTimer = null
  }

  if (window.Echo) {
    try {
      // gracefully leave channels we joined
      if (typeof (window.Echo as any).leave === 'function') {
        ;(window.Echo as any).leave('admin.orders')
        ;(window.Echo as any).leave('admin.service-requests')
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
    border-left: 4px solid var(--color-woosoo-green);
    background-color: color-mix(in srgb, var(--color-woosoo-green) 5%, transparent);
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
      <div class="space-y-5">
        <!-- Hero header -->
        <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-woosoo-accent/10 via-transparent to-transparent dark:from-woosoo-accent/6" />
          <div class="relative flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-2">
              <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                Kitchen Dispatch
              </span>
              <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                Orders
              </h2>
              <p class="text-sm text-muted-foreground">
                Live kitchen queue by status — updates in real time when connected.
              </p>
            </div>
          </div>
        </section>

        <!-- WebSocket connection status pill -->
        <div class="flex items-center justify-end">
          <span
            :class="[
              'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
              echoStatus === 'connected'    && 'bg-woosoo-green/10 text-woosoo-green',
              echoStatus === 'connecting'   && 'bg-woosoo-accent/10 text-woosoo-primary-dark',
              echoStatus === 'disconnected' && 'bg-destructive/10 text-destructive',
            ]"
          >
            <span
              :class="[
                'h-1.5 w-1.5 rounded-full',
                echoStatus === 'connected'    && 'bg-woosoo-green',
                echoStatus === 'connecting'   && 'bg-woosoo-accent animate-pulse',
                echoStatus === 'disconnected' && 'bg-destructive',
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
                              class="h-5 min-w-5 rounded-full bg-woosoo-accent px-1.5 text-xs tabular-nums text-woosoo-dark-gray"
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
                <TabsContent value="live_orders" class="space-y-4 pt-3">
                  <!-- Kanban toolbar: filters + refresh -->
                  <div class="flex flex-wrap items-center gap-2">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span class="text-xs font-medium text-muted-foreground mr-1">Status:</span>
                      <Button
                        v-for="opt in kanbanStatusOptions"
                        :key="opt.value"
                        variant="outline"
                        size="sm"
                        class="h-7 text-xs"
                        :class="kanbanStatusFilter.includes(opt.value) ? 'border-woosoo-accent bg-woosoo-accent/10' : ''"
                        @click="toggleKanbanStatus(opt.value)"
                      >
                        {{ opt.label }}
                      </Button>
                    </div>
                    <select
                      v-model="kanbanTableFilter"
                      class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                      aria-label="Filter by table"
                    >
                      <option value="all">All tables</option>
                      <option v-for="name in kanbanTableOptions" :key="name" :value="name">{{ name }}</option>
                    </select>
                    <select
                      v-model="kanbanTimeRange"
                      class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                      aria-label="Filter by time"
                    >
                      <option value="all">All time</option>
                      <option value="today">Today</option>
                      <option value="hour">Last hour</option>
                    </select>
                    <Button variant="outline" size="sm" class="h-8" aria-label="Refresh orders" @click="handleKanbanRefresh">
                      <RefreshCw class="h-4 w-4" />
                    </Button>
                  </div>
                  <!-- Summary chip row -->
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full border border-woosoo-accent/30 bg-woosoo-accent/10 px-3 py-1.5 text-xs font-medium text-foreground">
                      {{ dispatchSummary.confirmed }} confirmed
                      <span class="mx-1.5 text-muted-foreground">·</span>
                      {{ dispatchSummary.completed }} completed
                      <span class="mx-1.5 text-muted-foreground">·</span>
                      {{ dispatchSummary.voidedCancelled }} voided/cancelled
                    </span>
                  </div>

                  <!-- Kanban columns -->
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div
                      v-for="column in kanbanColumns"
                      :key="column.key"
                      class="flex min-h-[320px] flex-col rounded-[18px] border border-black/8 bg-white/50 dark:border-white/10 dark:bg-white/[0.03]"
                    >
                      <div class="flex items-center justify-between border-b border-black/8 px-4 py-3 dark:border-white/10">
                        <h3 class="text-[10px] font-semibold tracking-[0.2em] text-muted-foreground uppercase">
                          {{ column.label }}
                        </h3>
                        <Badge
                          variant="secondary"
                          class="h-5 min-w-5 rounded-full px-1.5 text-xs tabular-nums"
                        >
                          {{ column.count }}
                        </Badge>
                      </div>

                      <div class="flex flex-1 flex-col gap-2 overflow-y-auto p-3">
                        <button
                          v-for="order in column.orders"
                          :key="order.id ?? order.order_id ?? order.order_number"
                          type="button"
                          :data-order-id="order.id ?? order.order_id"
                          class="group w-full rounded-[14px] border border-black/8 bg-card p-3 text-left transition-all hover:border-woosoo-accent/40 hover:shadow-sm dark:border-white/10"
                          :class="{ 'ring-1 ring-woosoo-accent/30': order.__is_refill }"
                          @click="openOrderDetail(order)"
                        >
                          <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-bold text-foreground">ORD-{{ order.order_number }}</span>
                            <OrderStatusBadge :status="orderStatusKey(order)" class="shrink-0 text-[10px]" />
                          </div>
                          <p class="mt-1.5 truncate text-xs text-muted-foreground">
                            {{ order.device?.name ?? '—' }}
                            <span v-if="order.table?.name"> · {{ order.table.name }}</span>
                          </p>
                          <p class="mt-1 truncate text-xs text-foreground/80">
                            {{ packageLabel(order) }}
                            <span class="text-muted-foreground"> · {{ order.guest_count ?? '—' }} pax</span>
                          </p>
                          <p v-if="orderItemsPreview(order).length" class="mt-1.5 space-y-0.5 text-xs text-muted-foreground">
                            <span
                              v-for="(item, idx) in orderItemsPreview(order)"
                              :key="idx"
                              class="block truncate"
                            >
                              {{ item.name }} ×{{ item.quantity }}
                            </span>
                          </p>
                          <div class="mt-2 flex items-center justify-between gap-2 border-t border-black/5 pt-2 dark:border-white/8">
                            <span class="text-sm font-semibold tabular-nums text-woosoo-accent">
                              {{ formatCurrency(orderTotal(order)) }}
                            </span>
                            <span class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                              {{ formatElapsed(order.created_at) }}
                            </span>
                          </div>
                        </button>

                        <p
                          v-if="column.orders.length === 0"
                          class="flex flex-1 items-center justify-center py-8 text-center text-xs text-muted-foreground"
                        >
                          No {{ column.label.toLowerCase() }} orders
                        </p>
                      </div>
                    </div>
                  </div>
                </TabsContent>
                <TabsContent value="order_history" class="space-y-4 pt-3">
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
              @void="handleDetailVoid"
              @cancel-order="handleDetailCancel"
            />
        </div>
    </AppLayout>
</template>
