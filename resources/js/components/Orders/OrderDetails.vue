<script setup lang="ts">
import { computed } from 'vue';
import { format, parseISO } from 'date-fns';
import { formatCurrency } from '@/lib/utils';
import type { DeviceOrder } from '@/types/models';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

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
  note?: string | null;
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
const initialItems = computed(() => items.value.filter((it) => {
  // Check is_refill flag OR if notes contain "Refill"
  const hasRefillFlag = !!it?.is_refill;
  const hasRefillNote = String(it?.notes || it?.note || '').toLowerCase().includes('refill');
  return !hasRefillFlag && !hasRefillNote;
}));
const refillItems = computed(() => items.value.filter((it) => {
  // Check is_refill flag OR if notes contain "Refill"
  const hasRefillFlag = !!it?.is_refill;
  const hasRefillNote = String(it?.notes || it?.note || '').toLowerCase().includes('refill');
  return hasRefillFlag || hasRefillNote;
}));

// Group refill items by their creation timestamp (items created at the same time were submitted together)
const groupedRefills = computed(() => {
  const groups = new Map<string, typeof refillItems.value>();

  refillItems.value.forEach(item => {
    const timestamp = item.created_at || 'unknown';
    if (!groups.has(timestamp)) {
      groups.set(timestamp, []);
    }
    groups.get(timestamp)!.push(item);
  });

  // Convert to array and sort by timestamp (newest first)
  return Array.from(groups.entries())
    .sort(([a], [b]) => {
      if (a === 'unknown') return 1;
      if (b === 'unknown') return -1;
      return new Date(b).getTime() - new Date(a).getTime();
    })
    .map(([timestamp, items]) => ({
      timestamp,
      items,
      totalQty: items.reduce((sum, item) => sum + (item.quantity ?? 0), 0)
    }));
});
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

const formatDate = (dateString: string | null | undefined) => {
  if (!dateString) return '-';
  try {
    return format(parseISO(dateString), 'MMM dd, yyyy hh:mm a');
  } catch {
    return dateString;
  }
};

const itemLabel = (item: OrderItem) => {
  return item?.menu?.name ?? item?.name ?? (item?.menu_id ? `Menu #${item.menu_id}` : `Item #${item?.id ?? '-'}`);
};

const totalInitialItems = computed(() => {
  return initialItems.value.reduce((sum, item) => sum + (item?.quantity ?? 0), 0);
});
</script>

<template>
  <div v-if="loading" class="flex items-center justify-center py-12">
    <div class="text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
      <p class="text-sm text-muted-foreground">Loading order details...</p>
    </div>
  </div>

  <div v-else-if="error" class="rounded-md border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
    <div class="font-medium mb-1">Error Loading Order</div>
    <div>{{ error }}</div>
  </div>

  <div v-else-if="!order" class="flex items-center justify-center py-12 text-muted-foreground">
    No order selected
  </div>

  <Tabs v-else default-value="summary" class="w-full">
    <TabsList class="grid w-full grid-cols-3">
      <TabsTrigger value="summary">Summary</TabsTrigger>
      <TabsTrigger value="items">Items</TabsTrigger>
      <TabsTrigger value="history">History</TabsTrigger>
    </TabsList>

    <TabsContent value="summary" class="space-y-6 mt-6">
    <!-- Order Summary Card -->
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center justify-between">
          <span>Order Summary</span>
          <Badge :variant="order.is_printed ? 'default' : 'secondary'">
            {{ order.is_printed ? 'Printed' : 'Not Printed' }}
          </Badge>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div class="grid grid-cols-1 gap-3 text-sm md:grid-cols-2 lg:grid-cols-3">
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Order Number</span>
            <span class="font-semibold text-lg">{{ order.order_number }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Order ID</span>
            <span class="font-medium">{{ order.order_id ?? '-' }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Status</span>
            <Badge variant="outline" class="w-fit mt-1">{{ order.status }}</Badge>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Device</span>
            <span class="font-medium">{{ order.device?.name ?? order.device_id }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Table</span>
            <span class="font-medium">{{ order.table?.name ?? order.table_id ?? '-' }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Guests</span>
            <span class="font-medium">{{ order.guest_count }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Created At</span>
            <span class="font-medium">{{ formatDate(order.created_at) }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Updated At</span>
            <span class="font-medium">{{ formatDate(order.updated_at) }}</span>
          </div>
          <div class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Printed At</span>
            <span class="font-medium">{{ formatDate(order.printed_at) }}</span>
          </div>
          <div v-if="order.printed_by" class="flex flex-col">
            <span class="text-xs text-muted-foreground uppercase tracking-wide">Printed By</span>
            <span class="font-medium">{{ order.printed_by }}</span>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Order Totals Card -->
    <Card>
      <CardHeader>
        <CardTitle>Order Totals</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-2">
          <div class="flex justify-between text-sm">
            <span class="text-muted-foreground">Subtotal:</span>
            <span class="font-medium">{{ formatMoney((order as any).subtotal ?? (order as any).sub_total) }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-muted-foreground">Tax:</span>
            <span class="font-medium">{{ formatMoney(order.tax) }}</span>
          </div>
          <div class="flex justify-between text-sm">
            <span class="text-muted-foreground">Discount:</span>
            <span class="font-medium">{{ formatMoney(order.discount) }}</span>
          </div>
          <div class="flex justify-between text-base font-semibold pt-2 border-t">
            <span>Total:</span>
            <span class="text-lg">{{ formatMoney(order.total) }}</span>
          </div>
        </div>
      </CardContent>
    </Card>
    </TabsContent>

    <TabsContent value="items" class="space-y-6 mt-6">
    <!-- Initial Order Items Card -->
    <Card>
      <CardHeader>
        <CardTitle class="flex items-center justify-between">
          <span>Initial Order Items</span>
          <Badge variant="default">{{ initialItems.length }} items ({{ totalInitialItems }} qty)</Badge>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div v-if="!initialItems.length" class="text-center py-8 text-muted-foreground">
          No initial items found
        </div>
        <div v-else class="border rounded-md">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Item</TableHead>
                <TableHead class="text-center">Quantity</TableHead>
                <TableHead class="text-right">Price</TableHead>
                <TableHead class="text-right">Subtotal</TableHead>
                <TableHead class="text-right">Tax</TableHead>
                <TableHead class="text-right">Total</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow
                v-for="(item, idx) in initialItems"
                :key="item.id ?? item.menu_id ?? idx"
                :class="{ 'bg-blue-50/30': Number(item.price || 0) > 0, 'bg-gray-50/50': Number(item.price || 0) === 0 }"
              >
                <TableCell class="font-medium">
                  <div class="flex flex-col gap-1">
                    <div class="flex items-center gap-2">
                      <span>{{ itemLabel(item) }}</span>
                      <Badge v-if="Number(item.price || 0) === 0" variant="secondary" class="text-xs">Included</Badge>
                      <Badge v-if="Number(item.price || 0) > 0" variant="default" class="text-xs">Package</Badge>
                    </div>
                    <div v-if="item.notes" class="text-xs text-muted-foreground">
                      Note: {{ item.notes }}
                    </div>
                  </div>
                </TableCell>
                <TableCell class="text-center">
                  <Badge variant="outline">{{ item.quantity ?? '-' }}</Badge>
                </TableCell>
                <TableCell class="text-right">{{ formatMoney(item.price) }}</TableCell>
                <TableCell class="text-right">{{ formatMoney(item.subtotal) }}</TableCell>
                <TableCell class="text-right">{{ formatMoney(item.tax) }}</TableCell>
                <TableCell class="text-right font-medium">{{ formatMoney(item.total) }}</TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </div>
      </CardContent>
    </Card>

    <!-- Refill Items Card - Grouped by submission time -->
    <div v-if="groupedRefills.length > 0" class="space-y-4">
      <div v-for="(group, groupIdx) in groupedRefills" :key="groupIdx">
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span>Refill #{{ groupedRefills.length - groupIdx }}</span>
                <Badge variant="destructive" class="text-xs">{{ group.items.length }} items ({{ group.totalQty }} qty)</Badge>
              </div>
              <span class="text-sm font-normal text-muted-foreground">{{ formatDate(group.timestamp) }}</span>
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div class="border rounded-md">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Item</TableHead>
                    <TableHead class="text-center">Quantity</TableHead>
                    <TableHead class="text-right">Price</TableHead>
                    <TableHead class="text-right">Subtotal</TableHead>
                    <TableHead class="text-right">Tax</TableHead>
                    <TableHead class="text-right">Total</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="(item, idx) in group.items" :key="item.id ?? item.menu_id ?? idx" class="bg-red-50/50">
                    <TableCell class="font-medium">
                      <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                          <span>{{ itemLabel(item) }}</span>
                          <Badge variant="destructive" class="text-xs">Refill</Badge>
                        </div>
                        <div v-if="item.notes" class="text-xs text-muted-foreground">
                          Note: {{ item.notes }}
                        </div>
                      </div>
                    </TableCell>
                    <TableCell class="text-center">
                      <Badge variant="outline">{{ item.quantity ?? '-' }}</Badge>
                    </TableCell>
                    <TableCell class="text-right">{{ formatMoney(item.price) }}</TableCell>
                    <TableCell class="text-right">{{ formatMoney(item.subtotal) }}</TableCell>
                    <TableCell class="text-right">{{ formatMoney(item.tax) }}</TableCell>
                    <TableCell class="text-right font-medium">{{ formatMoney(item.total) }}</TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
    </TabsContent>

    <TabsContent value="history" class="space-y-6 mt-6">
    <!-- Refill History Card -->
    <Card v-if="refillEvents.length > 0">
      <CardHeader>
        <CardTitle>Refill History</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-3">
          <div v-for="evt in refillEvents" :key="evt.id" class="rounded-md border p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <div class="font-medium">Refill Event #{{ evt.id }}</div>
              <div class="text-sm text-muted-foreground">{{ formatDate(evt.created_at) }}</div>
            </div>
            <div class="flex flex-wrap gap-4 text-xs text-muted-foreground">
              <div>
                <span class="font-medium">Acknowledged:</span> {{ formatDate(evt.acknowledged_at) }}
              </div>
              <div>
                <span class="font-medium">Printer:</span> {{ evt.printer_name ?? '-' }}
              </div>
              <div v-if="evt.acknowledged_by_device_id">
                <span class="font-medium">Device:</span> #{{ evt.acknowledged_by_device_id }}
              </div>
            </div>
            <div v-if="evt.meta?.items?.length" class="mt-3 pt-3 border-t">
              <div class="text-xs font-medium text-muted-foreground mb-2">Refill Items:</div>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div v-for="(refillItem, idx) in evt.meta?.items" :key="idx" class="flex items-center justify-between text-sm p-2 rounded bg-muted/50">
                  <span>{{ refillItem.name ?? `Menu #${refillItem.menu_id ?? '-'}` }}</span>
                  <Badge variant="secondary" class="text-xs">Qty: {{ refillItem.quantity ?? '-' }}</Badge>
                </div>
              </div>
            </div>
            <div v-else class="text-xs text-muted-foreground">
              No refill items captured
            </div>
          </div>
        </div>
      </CardContent>
    </Card>

    <!-- Service Requests Card -->
    <Card v-if="serviceRequests.length > 0">
      <CardHeader>
        <CardTitle>Service Requests</CardTitle>
      </CardHeader>
      <CardContent>
        <div class="space-y-2">
          <div v-for="(req, idx) in serviceRequests" :key="idx" class="flex items-center justify-between p-3 rounded-md border">
            <div class="flex flex-col">
              <span class="font-medium">{{ req.table_service_name ?? 'Service Request' }}</span>
              <span class="text-xs text-muted-foreground">Order ID: {{ req.order_id ?? '-' }}</span>
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
    </TabsContent>
  </Tabs>
</template>
