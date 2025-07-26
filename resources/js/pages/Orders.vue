<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, reactive, computed  } from 'vue';
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
const ordersMap = reactive<Record<number, DeviceOrder>>({});
// 🧠 Find and update an order by ID
// function updateOrder(order: DeviceOrder) {
//   const index = reactiveOrders.value.findIndex(o => o.id === order.id);
//   if (index !== -1) {
//     reactiveOrders.value[index] = { ...reactiveOrders.value[index], ...order };
//   }

//   console.log(reactiveOrders.value);
// }

// const updateOrder = (order: DeviceOrder) => {
//   const existingOrder = reactiveOrders.value.find(o => o.id === order.id);
//   if (existingOrder) {
//     Object.assign(existingOrder, order); // Mutate directly (Vue tracks this)
//     console.log('Order updated:', order);
//   }
// };


// 🧩 Handle new or updated orders
// const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
//   if (isUpdate) {
//     updateOrder(event);
//     console.log('Order updated:', event);
//   } else {
//      addOrder(event);
//     // Prevent duplicate
//     if (!reactiveOrders.value.some(o => o.id === event.id)) {
//       reactiveOrders.value.unshift(event); // Push to top
//       console.log('New order created:', event);
//     }
//   }
// };

watch(reactiveOrders, (val) => {``
  console.log('Orders changed!', val);
});

const updateOrder = (order: DeviceOrder) => {
  if (ordersMap[order.id]) {
    Object.assign(ordersMap[order.id], order);
    console.log('Order updated:', order);
  }
};

// Add new order — does NOT trigger deep watchers on existing data
const addOrder = (order: DeviceOrder) => {
  if (!ordersMap[order.id]) {
    ordersMap[order.id] = order;
    console.log('New order added:', order);
  }
};

const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
  console.log(event); 
  if (isUpdate) {
    updateOrder(event);
  } else {
    addOrder(event);
  }
};

// Computed array version if needed for rendering
const ordersList = computed(() => Object.values(ordersMap));

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
});

onUnmounted(() => {
  if (window.Echo) {
    console.log('Display.vue unmounted. Leaving channels.');
  }
});
</script>

<template>

  <Head :title="title" :description="description" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6">  
      <AppTable :rows="reactiveOrders" :columns="columns" :filter="false" />
    </div>
  </AppLayout>
</template>
