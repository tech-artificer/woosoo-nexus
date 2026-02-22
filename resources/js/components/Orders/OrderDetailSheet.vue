<script setup lang="ts">
import { computed } from 'vue'
import type { DeviceOrder, OrderedMenu } from '@/types/models'
import { formatCurrency } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Clock, Printer, Receipt, RotateCcw, Users } from 'lucide-vue-next'

type OrderItem = OrderedMenu & {
  is_refill?: boolean
  type?: string
  status?: string
  created_at?: string
  delay_minutes?: number
}

interface OrderDetailSheetProps {
  open: boolean
  order: DeviceOrder | null
}

const props = defineProps<OrderDetailSheetProps>()
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
  (e: 'print'): void
  (e: 'complete'): void
}>()

const openState = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
})

const order = computed(() => props.order)

const safeText = (value: any, fallback = '-') => (value ?? value === 0 ? String(value) : fallback)

const formatDateTime = (value?: string | null) => {
  if (!value) return '-'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'
  return date.toLocaleString()
}

const getItems = (value: DeviceOrder | null): OrderItem[] => {
  // Accept multiple shapes returned by different endpoints or projections.
  const direct = value?.items
  if (Array.isArray(direct) && direct.length) return direct as OrderItem[]

  const alt1 = value?.orderedMenus
  if (Array.isArray(alt1) && alt1.length) return alt1 as OrderItem[]

  const alt2 = value?.order?.orderedMenus
  if (Array.isArray(alt2) && alt2.length) return alt2 as OrderItem[]

  const alt3 = value?.order_items || value?.order?.items
  if (Array.isArray(alt3) && alt3.length) return alt3 as OrderItem[]

  // As a final attempt, normalize objects that look like orderedMenus
  const possible = direct || alt1 || alt2 || alt3
  if (Array.isArray(possible)) {
    return (possible as any[]).map((it: any) => ({
      name: it.name || it.menu?.receipt_name || it.menu?.name || it.title || null,
      quantity: it.quantity ?? it.qty ?? 1,
      is_refill: !!it.is_refill || String(it.type || '').toLowerCase() === 'refill' || (it.name && String(it.name).toLowerCase().includes('refill')) || (it.notes && String(it.notes).toLowerCase().includes('refill')),
      type: it.type,
      status: it.status,
      created_at: it.created_at || it.createdAt || null,
      delay_minutes: it.delay_minutes ?? it.delayMinutes ?? null,
      menu: it.menu || null,
    })) as OrderItem[]
  }

  return []
}

const items = computed(() => getItems(order.value))

const isRefillItem = (item: OrderItem) => {
  if (item.is_refill) return true
  if (item.type && String(item.type).toLowerCase() === 'refill') return true
  if (item.name && String(item.name).toLowerCase().includes('refill')) return true
  if ((item as any).notes && String((item as any).notes).toLowerCase().includes('refill')) return true
  return false
}

const initialItems = computed(() => items.value.filter(item => !isRefillItem(item)))
const refillItems = computed(() => items.value.filter(item => isRefillItem(item)))

const totalAmount = computed(() => {
  const raw = order.value?.total ?? order.value?.meta?.order_check?.total_amount
  return typeof raw === 'number' ? raw : Number(raw || 0)
})

const subtotalAmount = computed(() => {
  // device_orders.subtotal is populated at order creation time
  const raw = order.value?.subtotal ?? order.value?.sub_total
  return typeof raw === 'number' ? raw : Number(raw || 0)
})

const taxAmount = computed(() => {
  const raw = order.value?.tax
  return typeof raw === 'number' ? raw : Number(raw || 0)
})

const statusVariant = (status?: string) => {
  const normalized = String(status || '').toLowerCase()
  if (['completed', 'served'].includes(normalized)) return 'success'
  if (['voided', 'cancelled'].includes(normalized)) return 'destructive'
  if (['confirmed', 'ready'].includes(normalized)) return 'active'
  return 'secondary'
}

const itemStatusLabel = (item: OrderItem) => {
  const value = String(item.status || '').toLowerCase()
  // If item has a meaningful status beyond the default, show it
  if (value && value !== 'pending') return value.replace(/_/g, ' ')
  // Fall back to the parent order's status so items reflect real order state
  const orderStatus = String((order.value as any)?.status || '').toLowerCase()
  return orderStatus || 'pending'
}
</script>

<template>
  <Sheet v-model:open="openState">
    <SheetContent side="right" class="w-full overflow-y-auto sm:max-w-5xl lg:max-w-[92vw]">
      <SheetHeader>
        <SheetTitle class="text-xl">Order {{ safeText(order?.order_number) }}</SheetTitle>
        <SheetDescription>
          Table {{ safeText(order?.table?.name) }} - Started {{ formatDateTime(order?.created_at) }}
        </SheetDescription>
      </SheetHeader>

      <div class="mt-6 flex flex-col gap-6 px-4 pb-6 sm:px-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2">
            <Badge :variant="statusVariant(order?.status)">{{ safeText(order?.status, 'unknown') }}</Badge>
            <Badge variant="outline">Order ID: {{ safeText(order?.order_id) }}</Badge>
            <Badge variant="outline">Device: {{ safeText(order?.device?.name) }}</Badge>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <Button variant="outline" size="sm" @click="emit('print')">
              <Printer class="mr-2 size-4" /> Print Bill
            </Button>
            <Button size="sm" @click="emit('complete')">Complete Transaction</Button>
          </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1.05fr_1.15fr_1.25fr]">
          <div class="flex flex-col gap-4">
            <Card>
              <CardHeader>
                <CardTitle class="text-sm text-muted-foreground">Order Overview</CardTitle>
                <CardDescription class="text-xl font-semibold text-foreground">
                  {{ safeText(order?.name, 'Table Order') }}
                </CardDescription>
              </CardHeader>
              <CardContent class="space-y-3">
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center gap-2 text-muted-foreground">
                    <Users class="size-4" /> Guests
                  </div>
                  <div class="font-medium">{{ safeText(order?.guest_count, '0') }}</div>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center gap-2 text-muted-foreground">
                    <Clock class="size-4" /> Timer
                  </div>
                  <div class="font-medium">{{ formatDateTime(order?.created_at) }}</div>
                </div>
                <Separator />
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center gap-2 text-muted-foreground">
                    <Receipt class="size-4" /> Subtotal
                  </div>
                  <div class="font-semibold">{{ formatCurrency(subtotalAmount) }}</div>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center gap-2 text-muted-foreground">
                    <Receipt class="size-4" /> Tax
                  </div>
                  <div class="font-semibold">{{ formatCurrency(taxAmount) }}</div>
                </div>
                <div class="flex items-center justify-between text-sm">
                  <div class="flex items-center gap-2 text-muted-foreground">
                    <Receipt class="size-4" /> Total
                  </div>
                  <div class="text-lg font-semibold">{{ formatCurrency(totalAmount) }}</div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle class="text-sm text-muted-foreground">Live Insights</CardTitle>
                <CardDescription>Operational snapshot</CardDescription>
              </CardHeader>
              <CardContent class="space-y-3 text-sm">
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">Initial items</span>
                  <span class="font-medium">{{ initialItems.length }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">Refill count</span>
                  <span class="font-medium">{{ refillItems.length }}</span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-muted-foreground">Service requests</span>
                  <span class="font-medium">{{ safeText(order?.service_requests?.length, '0') }}</span>
                </div>
                <div class="flex items-center gap-2 text-xs text-muted-foreground">
                  <RotateCcw class="size-4" /> Realtime status sync enabled
                </div>
              </CardContent>
            </Card>
          </div>

          <Card class="h-full">
            <CardHeader>
              <CardTitle class="text-sm text-muted-foreground">Initial Tray</CardTitle>
              <CardDescription>Starter fulfillment</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div v-if="initialItems.length === 0" class="text-sm text-muted-foreground">
                No starter items found.
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="(item, index) in initialItems"
                  :key="index"
                  class="flex items-center justify-between rounded-lg border px-3 py-2"
                >
                  <div class="space-y-1">
                    <div class="text-sm font-medium">
                      {{ safeText(item.name || item.menu?.receipt_name || item.menu?.name, 'Item') }}
                    </div>
                    <div class="text-xs text-muted-foreground">Qty {{ safeText(item.quantity, '0') }}</div>
                  </div>
                  <Badge :variant="statusVariant(item.status)">{{ itemStatusLabel(item) }}</Badge>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card class="h-full">
            <CardHeader>
              <CardTitle class="text-sm text-muted-foreground">Refill Monitor</CardTitle>
              <CardDescription>Tablet-driven requests</CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
              <div class="grid grid-cols-[2fr_0.5fr_1fr_1fr] text-xs font-semibold text-muted-foreground">
                <div>Item</div>
                <div>Qty</div>
                <div>Status</div>
                <div>Delay</div>
              </div>
              <Separator />
              <div v-if="refillItems.length === 0" class="text-sm text-muted-foreground">
                No refill items yet.
              </div>
              <div v-else class="space-y-3">
                <div
                  v-for="(item, index) in refillItems"
                  :key="index"
                  class="grid grid-cols-[2fr_0.5fr_1fr_1fr] items-center gap-2 text-sm"
                >
                  <div class="truncate">{{ safeText(item.name || item.menu?.receipt_name || item.menu?.name, 'Item') }}</div>
                  <div>{{ safeText(item.quantity, '0') }}</div>
                  <Badge :variant="statusVariant(item.status)">{{ itemStatusLabel(item) }}</Badge>
                  <div class="text-muted-foreground">{{ safeText(item.delay_minutes, '-') }}</div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </SheetContent>
  </Sheet>
</template>
