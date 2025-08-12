<script setup lang="ts">
import TableCard from "@/pages/table/TableCard.vue"
import { DeviceOrder } from "@/types/models";


defineProps<{
    orders: DeviceOrder[]; // You can refine this to Order[] if needed
}>()

// interface Table {
//   tableNumber: string
//   guestCount: number
//   time: string
//   price: number
//   status: boolean
//   paid: boolean
// }

// const tables: Table[] = [
//   { tableNumber: "02", guestCount: 5, time: "12:00", price: 19.99, status: true, paid: false },
//   { tableNumber: "05", guestCount: 3, time: "12:15", price: 35.50, status: true, paid: true },
//   { tableNumber: "09", guestCount: 2, time: "12:30", price: 15.00, status: false, paid: true },
//   { tableNumber: "12", guestCount: 5, time: "12:45", price: 55.00, status: true, paid: false },
// ]

</script>

<template>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4">
    <!-- <pre>{{ orders }}</pre> -->
    
    <TableCard
      v-for="deviceOrder in orders"
      :key="deviceOrder.order_id"
      v-bind="deviceOrder"
      name="deviceOrder.table?.name"
    />
  </div>
</template>
