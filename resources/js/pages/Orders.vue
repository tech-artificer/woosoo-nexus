<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch  } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { DeviceOrder } from '@/types/models';
import { getOrderColumns } from '@/pages/order/order-columns';
import AppTable from '@/components/datatable/AppTable.vue';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Orders',
    href: '/orders',
  },
];

const columns = getOrderColumns();

const props = defineProps<{
  title?: string;
  description?: string;
  user: any;
  orders: DeviceOrder[]; // You can refine this to Order[] if needed
}>()

// 🔁 Make the orders reactive so we can push to it later
const reactiveOrders = ref<DeviceOrder[]>([...props.orders]);

// 🧠 Find and update an order by ID
function updateOrder(order: DeviceOrder) {
  const index = reactiveOrders.value.findIndex(o => o.id === order.id);
  if (index !== -1) {
    reactiveOrders.value[index] = { ...reactiveOrders.value[index], ...order };
  }

  console.log(reactiveOrders.value);
}

// 🧩 Handle new or updated orders
const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
  if (isUpdate) {
    updateOrder(event);
    console.log('Order updated:', event);
  } else {
    // Prevent duplicate
    if (!reactiveOrders.value.some(o => o.id === event.id)) {
      reactiveOrders.value.unshift(event); // Push to top
      console.log('New order created:', event);
    }
  }
};

watch(reactiveOrders, (val) => {``
  console.log('Orders changed!', val);
});

onMounted(() => {
  console.log('Display.vue mounted. Joining "admin.orders" channel.');

  if (!window.Echo) {
    console.error('Display.vue: window.Echo is not available.');
    return;
  }

  if (props.user.is_admin) {
    window.Echo.private('admin.orders')
      .listen('.order.created', (e: DeviceOrder) => handleOrderEvent(e, false))
      .listen('.order.completed', (e: DeviceOrder) => handleOrderEvent(e, true))
      .error((error: DeviceOrder) => {
        console.error('Error connecting to admin.orders channel:', error);
      });
  }
//   console.log('Orders:', props.user);
//   window.Echo.channel(`orders`)
//     .listen('.order.created', (e: any) => handleOrderEvent(e, false))
//     .listen('.order.completed', (e: any) => handleOrderEvent(e, true))
//     .error((error: any) => {
//       console.error('Error connecting to orders.1 channel:', error);
//     });
});

onUnmounted(() => {
  if (window.Echo) {
    console.log('Display.vue unmounted. Leaving channels.');
    window.Echo.leave('admin.orders');
  }
});
</script>

<template>

  <Head :title="title" :description="description" />

  <AppLayout :breadcrumbs="breadcrumbs">
 <div class="p-6">  
 

   <AppTable :rows="reactiveOrders" :columns="columns" :filter="false" />
       <!-- <Tabs default-value="orders" class="w-full">
        <TabsList>
          <TabsTrigger value="orders">Orders</TabsTrigger>
          <TabsTrigger value="table_orders">Table Orders</TabsTrigger>
        </TabsList>

        <TabsContent value="orders" class="flex h-full flex-1 flex-col gap-4 rounded-xl">
         
        </TabsContent>

        <TabsContent value="table_orders" class="p-4">
          Change your password here.
        </TabsContent>
      </Tabs> -->
    </div>
  </AppLayout>
</template>
