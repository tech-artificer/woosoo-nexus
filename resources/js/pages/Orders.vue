<script setup lang="ts">
// watch, computed  ref,
import { onMounted, onUnmounted, } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { DeviceOrder, ServiceRequest, TableOrder } from '@/types/models';
import { getOrderColumns } from '@/pages/order/order-columns';
import AppTable from '@/pages/order/OrderTable.vue';
import TablesGrid from '@/pages/table/TablesGrid.vue';



const columns = getOrderColumns();

const props = defineProps<{
  title?: string;
  description?: string;
  user: any;
  orders: DeviceOrder[]; // You can refine this to Order[] if needed,
  tableOrders: TableOrder[]

}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Orders',
    href: '/orders',
  },
    {
    title: 'Table Orders',
    href: '/orders/live',
  },
];

const form = useForm({

});

const fetchData = (url: string) => {
  form.get(url, {
    preserveState: true,
    replace: true, // Replace history entry
  });
}

const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {

  if( isUpdate ) {
    
  }
  console.log(event);
  fetchData(route('orders.live'));

};

onMounted(() => {
  if (!window.Echo) {
    console.error('Display.vue: window.Echo is not available.');
    return;
  }

  if (props.user.is_admin) {
    window.Echo.private('admin.orders')
      .listen('.order.created', (e: DeviceOrder) => handleOrderEvent(e, false))
      .listen('.order.completed', (e: DeviceOrder) => handleOrderEvent(e, true))
      .listen('.order.voided', (e: DeviceOrder) => handleOrderEvent(e, true))
      .error((error: DeviceOrder) => {
        console.error('Error connecting to admin.orders channel:', error);
      });

    window.Echo.private('admin.service-requests')
      .listen('.service-request.notification', (e: ServiceRequest) => {
        console.log(e);
        fetchData(route('orders.live'));
      })
      .error((error: ServiceRequest) => {
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
    <!-- <pre>{{ tableOrders }}</pre> -->

    <div class="p-6">
     <AppTable :rows="orders" :columns="columns" :filter="false" />
    </div>

    <!-- <div> -->
      <!-- <TablesGrid :orders="orders" /> -->
    <!-- </div>   -->

  </AppLayout>
</template>
