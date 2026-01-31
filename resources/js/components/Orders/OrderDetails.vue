<script setup lang="ts">
import { computed } from 'vue';
import { formatCurrency } from '@/lib/utils';
import type { DeviceOrder } from '@/types/models';

type OrderItem = {
  id?: number;
  menu_id?: number | null;
  menu?: { id?: number; name?: string; receipt_name?: string } | null;
  name?: string | null;
  quantity?: number | null;
  price?: number | null;
  subtotal?: number | null;
  tax?: number | null;
  total?: number | null;
  notes?: string | null;
  is_refill?: boolean | null;
  created_at?: string | null;
};

type PrintEvent = {
  id?: number;
  event_type?: string | null;
  meta?: { items?: Array<{ name?: string; quantity?: number; menu_id?: number }> } | null;
  created_at?: string | null;
  acknowledged_at?: string | null;
  printer_name?: string | null;
  acknowledged_by_device_id?: number | null;
};

type OrderDetails = Omit<DeviceOrder, 'items'> & {
  items?: OrderItem[];
  service_requests?: Array<Record<string, any>>;
  print_events?: PrintEvent[];
  device?: { id: number; name?: string | null } | null;
  table?: { id: number; name?: string | null } | null;
  table_id?: number | null;
  tax?: number | null;
  discount?: number | null;
  subtotal?: number | null;
  updated_at?: string | null;
  created_at?: string | null;
  printed_at?: string | null;
  printed_by?: string | null;
  is_printed?: boolean | null;
};

const props = withDefaults(defineProps<{ order: OrderDetails | null; loading?: boolean; error?: string | null }>(), {
  loading: false,
  error: null,
});

const items = computed(() => props.order?.items ?? []);
const refillItems = computed(() => items.value.filter((it) => !!it?.is_refill));
const refillEvents = computed(() => {
  const events = props.order?.print_events ?? [];
  return [...events]
    .filter((evt) => String(evt?.event_type ?? '').toLowerCase() === 'refill')
    .sort((a, b) => new Date(a?.created_at ?? 0).getTime() - new Date(b?.created_at ?? 0).getTime());
});
const serviceRequests = computed(() => props.order?.service_requests ?? []);

const formatMoney = (value: unknown) => {
  if (value === null || value === undefined || value === '') return '-';
  const numberValue = Number(value);
  if (Number.isNaN(numberValue)) return '-';
  return formatCurrency(numberValue);
};

const itemLabel = (item: OrderItem) => {
  return item?.menu?.name ?? item?.name ?? (item?.menu_id ? `Menu #${item.menu_id}` : `Item #${item?.id ?? '-'}`);
};
</script>

<template>
  <div v-if="loading" class="py-6 text-sm text-muted-foreground">
    Loading order details...
  </div>

  <div v-else-if="error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
    {{ error }}
  </div>

  <div v-else-if="!order" class="py-6 text-sm text-muted-foreground">
    No order selected.
  </div>

  <div v-else class="space-y-6">
    <div class="space-y-2">
      <h3 class="text-base font-semibold">Order Summary</h3>
      <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">
        <div><span class="text-muted-foreground">Order #:</span> {{ order.order_number }}</div>
        <div><span class="text-muted-foreground">Order ID:</span> {{ order.order_id ?? '-' }}</div>
        <div><span class="text-muted-foreground">Device:</span> {{ order.device?.name ?? order.device_id }}</div>
        <div><span class="text-muted-foreground">Table:</span> {{ order.table?.name ?? order.table_id ?? '-' }}</div>
        <div><span class="text-muted-foreground">Status:</span> {{ order.status }}</div>
        <div><span class="text-muted-foreground">Guests:</span> {{ order.guest_count }}</div>
        <div><span class="text-muted-foreground">Printed:</span> {{ order.is_printed ? 'Yes' : 'No' }}</div>
        <div><span class="text-muted-foreground">Printed At:</span> {{ order.printed_at ?? '-' }}</div>
        <div><span class="text-muted-foreground">Printed By:</span> {{ order.printed_by ?? '-' }}</div>
        <div><span class="text-muted-foreground">Created At:</span> {{ order.created_at }}</div>
        <div><span class="text-muted-foreground">Updated At:</span> {{ order.updated_at }}</div>
      </div>
    </div>

    <div class="space-y-2">
      <h3 class="text-base font-semibold">Totals</h3>
      <div class="grid grid-cols-1 gap-2 text-sm md:grid-cols-2">
        <div><span class="text-muted-foreground">Subtotal:</span> {{ formatMoney((order as any).subtotal ?? (order as any).sub_total) }}</div>
        <div><span class="text-muted-foreground">Tax:</span> {{ formatMoney(order.tax) }}</div>
        <div><span class="text-muted-foreground">Discount:</span> {{ formatMoney(order.discount) }}</div>
        <div><span class="text-muted-foreground">Total:</span> {{ formatMoney(order.total) }}</div>
      </div>
    </div>

    <div class="space-y-2">
      <h3 class="text-base font-semibold">Items</h3>
      <div v-if="!items.length" class="text-sm text-muted-foreground">No items found.</div>
      <div v-else class="divide-y rounded-md border">
        <div v-for="(item, idx) in items" :key="item.id ?? item.menu_id ?? idx" class="flex flex-col gap-1 p-3 text-sm">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="font-medium">
              {{ itemLabel(item) }}
              <span v-if="item.is_refill" class="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">Refill</span>
            </div>
            <div class="text-muted-foreground">Qty: {{ item.quantity ?? '-' }}</div>
          </div>
          <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
            <span>Price: {{ formatMoney(item.price) }}</span>
            <span>Subtotal: {{ formatMoney(item.subtotal) }}</span>
            <span>Tax: {{ formatMoney(item.tax) }}</span>
            <span>Total: {{ formatMoney(item.total) }}</span>
          </div>
          <div v-if="item.notes" class="text-xs text-muted-foreground">Note: {{ item.notes }}</div>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <h3 class="text-base font-semibold">Refill History</h3>
      <div v-if="!refillEvents.length" class="text-sm text-muted-foreground">No refill events recorded.</div>
      <div v-else class="space-y-3">
        <div v-for="evt in refillEvents" :key="evt.id" class="rounded-md border p-3 text-sm">
          <div class="flex flex-wrap justify-between gap-2">
            <div class="font-medium">Refill Event #{{ evt.id }}</div>
            <div class="text-muted-foreground">{{ evt.created_at ?? '-' }}</div>
          </div>
          <div class="mt-2 text-xs text-muted-foreground">
            Ack: {{ evt.acknowledged_at ?? 'Pending' }} | Printer: {{ evt.printer_name ?? '-' }} | Ack Device: {{ evt.acknowledged_by_device_id ?? '-' }}
          </div>
          <div class="mt-2">
            <div v-if="!(evt.meta?.items?.length)" class="text-xs text-muted-foreground">No refill items captured.</div>
            <ul v-else class="list-disc space-y-1 pl-4 text-xs">
              <li v-for="(refillItem, idx) in evt.meta?.items" :key="idx">
                {{ refillItem.name ?? `Menu #${refillItem.menu_id ?? '-'}` }} — Qty: {{ refillItem.quantity ?? '-' }}
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div class="space-y-2">
      <h3 class="text-base font-semibold">Refill Items (Local)</h3>
      <div v-if="!refillItems.length" class="text-sm text-muted-foreground">No refill items flagged in local table.</div>
      <ul v-else class="list-disc space-y-1 pl-4 text-sm">
        <li v-for="(item, idx) in refillItems" :key="item.id ?? item.menu_id ?? idx">
          {{ itemLabel(item) }} — Qty: {{ item.quantity ?? '-' }} ({{ item.created_at ?? 'no timestamp' }})
        </li>
      </ul>
    </div>

    <div class="space-y-2">
      <h3 class="text-base font-semibold">Service Requests</h3>
      <div v-if="!serviceRequests.length" class="text-sm text-muted-foreground">No service requests.</div>
      <ul v-else class="list-disc space-y-1 pl-4 text-sm">
        <li v-for="(req, idx) in serviceRequests" :key="idx">
          {{ req.table_service_name ?? 'Service' }} (Order ID: {{ req.order_id ?? '-' }})
        </li>
      </ul>
    </div>
  </div>
</template>
