<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { columns } from '@/components/Orders/columns';
import DataTable from '@/components/Orders/DataTable.vue'
import { DeviceOrder, ServiceRequest, User} from '@/types/models';
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs"
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
    orderHistory: DeviceOrder[];
}>()    

const handleOrderEvent = (event: DeviceOrder, isUpdate = false) => {
  console.log('Order event received:', event, 'Is update:', isUpdate);

  // Slight delay so Echo finishes its callback cleanly
  setTimeout(() => {
    router.visit(route('orders.index'));
  }, 50);

  // no need to return true here — it’s not a message listener
};

onMounted(() => {
  if (!window.Echo) {
    console.error('Display.vue: window.Echo is not available.');
    return;
  }

  if (!user.is_admin) return;

  console.log('Display.vue mounted. Joining channels.');

  window.Echo.channel('admin.orders')
    .listen('.order.created', (e: DeviceOrder) => handleOrderEvent(e, false))
    .listen('.order.completed', (e: DeviceOrder) => handleOrderEvent(e, true))
    .listen('.order.voided', (e: DeviceOrder) => handleOrderEvent(e, true))
    .error((error: unknown) => {
      console.error('Error connecting to admin.orders channel:', error);
    });

  window.Echo.channel('admin.service-requests')
    .listen('.service-request.notification', (e: ServiceRequest) => {
      console.log(e);
    })
    .error((error: unknown) => {
      console.error('Error connecting to admin.service-requests channel:', error);
    });
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
         <Tabs default-value="live_orders" class="">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="live_orders">
                        Live Orders
                    </TabsTrigger>
                    <TabsTrigger value="order_history">
                        Order History
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="live_orders" class="p-2">
                    <DataTable :data="orders" :columns="columns" />
                </TabsContent>
                <TabsContent value="order_history" class="p-2">
                    <DataTable :data="orderHistory" :columns="columns" />  
                </TabsContent>
            </Tabs>
        <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
           
        </div>
    </AppLayout>
</template>
