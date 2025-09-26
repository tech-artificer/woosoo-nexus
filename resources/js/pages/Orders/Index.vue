<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { columns } from '@/components/Orders/columns';
import DataTable from '@/components/Orders/DataTable.vue'
import { DeviceOrder, ServiceRequest, User} from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Orders',
        href: route('orders.index'),
    },
];

const page = usePage();

const user = page.props.auth.user as User;

defineProps<{
    title: string;
    description: string;
    orders: DeviceOrder[];
}>()    

const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
  console.log(event);
  if (isUpdate) {
    //
  }

  router.visit(route('orders.index'));

};

onMounted(() => {
  if (!window.Echo) {
    console.error('Display.vue: window.Echo is not available.');
    return;
  }

  if (user.is_admin) {
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
        // fetchData(route('orders.live'));
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
      <!-- <pre>
        {{ orders[0].items }}
      </pre> -->
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
             <DataTable :data="orders" :columns="columns" />
        </div>
    </AppLayout>
</template>
