<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
// import PlaceholderPattern from '@/components/PlaceholderPattern.vue';
import { Order } from '@/types/models';
// import { ordercolumns } from '@/pages/orders/columns';
// import AppTable from '@/components/datatable/AppTable.vue';
// import axios from 'axios';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Orders',
        href: '/orders',
    },
];

const props = defineProps<{
    title?: string;
    description?: string;
    user: any;
    orders: Object[];
    // deviceOrders: DeviceOrder[];
}>()

// const channelId = props.user.id;

const handleOrderEvent = (event: any, isUpdate = false) => {
 
  if(isUpdate) {
    if( event.status === 'completed') {
      console.log('completed');
    }else{
      console.log('confirmed');
    }
    // console.log('Order updated:', event);
  }else{
    // console.log('Order created:', event);
  }

  // const deviceOrder: Order = {
  //   id: event.id,
  // }
}

// const apiOrders = ref([]);

// const fetchOrders = async () => {
//     try {
// //         // Axios automatically includes session cookies and the X-CSRF-TOKEN header
// //         // because of the global configuration in bootstrap.js
//         const response = await axios.get('/api/orders');
//       console.log(response.data);

//     } catch (error) {
//         apiOrders.value = [];
//     }
// };

// Echo event listeners
onMounted(() => {
  console.log('Display.vue mounted. Joining "orders" channel.');
  
  if (!window.Echo) {
    console.error('Display.vue: window.Echo is not available.')
    return;
  }


  if (props.user.is_admin) {
    window.Echo.private('orders.admin')
      .listen('.order.created', handleOrderEvent)
      .listen('.order.completed', handleOrderEvent)
      .error((error: Order) => {
        console.error('Display.vue: Error connecting to Reverb channel:', error)
    })
  }

  // window.Echo.private(`orders.${channelId}`)
  window.Echo.private(`orders.1`)
    .listen('.order.created', handleOrderEvent)
    .listen('.order.completed',handleOrderEvent)
    .error((error: Order) => {
      console.error('Display.vue: Error connecting to Reverb channel:', error)
    })
});



onUnmounted(() => {
  if (window.Echo) {
    console.log('Display.vue unmounted. Leaving channel.')
    window.Echo.leave('orders')
  }
})

</script>

<template>
    <Head :title="title" :description="description" />
    
    <AppLayout :breadcrumbs="breadcrumbs">
      <div class="p-6">
      
      <Tabs default-value="orders" class="w-[400px]">
        <TabsList>
          <TabsTrigger value="orders">
            Orders
          </TabsTrigger>
          <TabsTrigger value="table_orders">
            Table Orders
          </TabsTrigger>
        </TabsList>
        <TabsContent value="orders" class="p-3">
          Make changes to your account here.
        </TabsContent>
        <TabsContent value="table_orders" class="p-4">
          Change your password here.
        </TabsContent>
      </Tabs>
      </div>
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="relative min-h-[100vh] flex-1 rounded-xl border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <!-- <AppTable :rows="orders" :columns="ordercolumns" /> -->
            </div>
        </div>
    </AppLayout>
</template>

