<script setup lang="ts">
import { computed } from "vue"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/components/ui/card"
import { Badge } from "@/components/ui/badge"
import { Users, Clock, Printer } from "lucide-vue-next"
import { DeviceOrder } from "@/types/models";
import ServiceRequestIcons from '@/pages/order/ServiceRequestIcons.vue';

// interface Props {
// deviceName: string;
//   tableNumber: string;
//   guestCount: number;
//   time: string;
//   price: number;
//   status: boolean;
//   paid: boolean;
// }

const props = defineProps<DeviceOrder>()

function checkout() {
  console.log(`Checkout table ${props.table?.name}`)
}

function printBill() {
  console.log(`Print bill for table ${props.table?.name}`)
}

// Dynamic badge styling
const statusBadgeClass = computed(() => {
  return props.status
    ? "bg-green-100 text-green-700 border border-green-300"
    : "bg-red-100 text-red-700 border border-red-300"
})

// const paymentBadgeClass = computed(() => {
//   return props.status
//     ? "bg-green-100 text-green-700 border border-green-300"
//     : "bg-orange-100 text-orange-700 border border-orange-300"
// })
</script>

<template>
    <Card class="w-full rounded-xl shadow-sm transition-all hover:shadow-md">
    <!-- Header -->
    <CardHeader>
        <CardTitle class="flex flex-row justify-between items-start sm:items-center gap-1">
            <h3 class="text-lg sm:text-base font-semibold ">Table {{ props.table?.name }}</h3>
            <Badge :class="statusBadgeClass">
                {{ !props.table?.is_available ? "Used" : "Available" }}
            </Badge>
        </CardTitle>

        <CardDescription class="text-sm text-muted-foreground block">
        Order # {{ props.order_number }}
        </CardDescription>
        <ServiceRequestIcons request="clean" class="p-0" />
    </CardHeader>

    <!-- Content -->
    <CardContent class="space-y-2 text-sm sm:text-base">
        <div class="flex items-center text-muted-foreground gap-1">
        <Users class="w-4 h-4 shrink-0" /> 
        <span>{{ props.order?.guest_count }} Guest</span>
        </div>
        <div class="flex items-center text-muted-foreground gap-1">
        <Clock class="w-4 h-4 shrink-0" /> 
        <span>{{ props.order?.date_time_opened }}</span>
        </div>
        <div class="text-base font-medium">
        ₱ {{ props.meta.order_check.total_amount.toLocaleString('locale', { minimumFractionDigits: 2 }) }}
        </div>
        <div>
        <!-- <Badge :class="paymentBadgeClass">
            {{ props.paid ? "Paid" : "Unpaid" }}
        </Badge> -->
        </div>
        <!-- <div class="flex flex-col sm:flex-row gap-2 pt-3">
        <ServiceRequestIcons request="clean" />
        </div> -->
        <div class="flex flex-col sm:flex-row gap-2 pt-3">
        <Button class="flex-1 bg-blue-100 text-blue-700 hover:bg-blue-200" @click="checkout"> 
            Mark as Completed
        </Button>
        <Button variant="outline" size="icon" @click="printBill">
            <Printer class="w-4 h-4" />
        </Button> 
        </div>

    </CardContent>
    </Card>
</template>
