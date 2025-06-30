<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Order } from '@/types/models';
import { ordercolumns } from '@/pages/orders/columns';
import OrderTable from '@/pages/orders/OrderTable.vue';
import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Orders',
        href: '/orders',
    },
];


defineProps<{
    title?: string;
    description?: string;
    orders: Order[];
}>()

const handleOrderEvent = (event: Order, isUpdate = false) => {

  console.log('Order event received:', event);
  const order = event

  // const order: Order = {
  //   id: event.id,
  // }
}

const apiOrders = ref([]);

const fetchOrders = async () => {
    try {
        // Axios automatically includes session cookies and the X-CSRF-TOKEN header
        // because of the global configuration in bootstrap.js
        const response = await axios.get('/api/orders');
      console.log(response.data);

    } catch (error) {
        apiOrders.value = [];
    }
};

// Echo event listeners
onMounted(() => {

  fetchOrders();
  
  if (!window.Echo) {
    console.error('Display.vue: window.Echo is not available.')
    return
  }

  window.Echo.channel('orders')
    .listen('.order.created', (event: Order) => {
      console.log('Display.vue: New order created:', event)
    })  
    .listen('.order.updated', (event: Order) => {
      console.log('Display.vue: order updated:', event)
    })
    .error((error: Order) => {
      console.error('Display.vue: Error connecting to Reverb channel:', error)
    })
})

onUnmounted(() => {
  if (window.Echo) {
    console.log('Display.vue unmounted. Leaving "orders" channel.')
    window.Echo.leave('orders')
  }
})

</script>

<template>
    <Head :title="title" :description="description" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
        <!-- <pre>
            {{ orders }}
        </pre> -->
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="relative min-h-[100vh] flex-1 rounded-xl border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <OrderTable :orders="orders" :columns="ordercolumns" />
            </div>
        </div>
    </AppLayout>
</template>
