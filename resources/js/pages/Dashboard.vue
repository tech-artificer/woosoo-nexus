<script setup lang="ts">
import { onMounted, computed } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import {
    type LucideIcon,
    ChartSpline,
    ArrowUp10,
    Tablet,
    CheckCircle2,
    Clock,
    AlertCircle,
    CalendarDays,
} from 'lucide-vue-next';

interface DashCards {
  title?: string;
  value?: string | number;
  icon?: LucideIcon;
  helpText?: string;
}

interface ReverbStatus {
    ok: boolean;
    host: string;
    port: number;
    latencyMs?: number;
    error?: string;
    checkedAt?: string;
}

interface DeviceInfo {
    id: number
    name: string
    device_id: string
    is_active: boolean
    table: any
    today_orders_count: number
    pending_orders_count: number
    last_order_at: string | null
    bluetooth_address: string | null
    printer_name: string | null
}

const props = defineProps<{
    title?: string
    description?: string
    tableOrders: any
    openOrders: any,
    sessionId: number,
    totalSales: string | number,
    guestCount: string | number,
    totalOrders: string | number,
    monthlySales: string | number,
    salesData?: any[],
    reverbStatus: ReverbStatus,
    devices?: DeviceInfo[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Filter devices with assigned tables
const devicesWithTables = computed(() =>
    props.devices?.filter(d => d.table) ?? []
);

const dashCards = computed<DashCards[]>(() => [
    {
        title: 'Total Sales Today',
        value: '₱' + props.totalSales,
        icon: ChartSpline,
        helpText: `Daily revenue`,
    },
    {
        title: `Today's Orders`,
        value: props.totalOrders,
        icon: ArrowUp10,
        helpText: 'Orders processed',
    },
    {
        title: `Active Devices`,
        value: devicesWithTables.value.filter(d => d.is_active).length ?? 0,
        icon: Tablet,
        helpText: 'Devices online',
    },
    {
        title: `Pending Orders`,
        value: devicesWithTables.value.reduce((sum, d) => sum + d.pending_orders_count, 0) ?? 0,
        icon: Clock,
        helpText: 'Needs attention',
    },
]);




onMounted(() => {
  
});


</script>

<template>

    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 class="text-2xl font-semibold text-gray-900">Dashboard</h1>
                        <p class="text-sm text-gray-500 mt-1">Real-time restaurant operations overview</p>
                        <div v-if="props.sessionId" class="flex items-center gap-2 mt-2">
                            <CalendarDays class="h-4 w-4 text-gray-400" />
                            <span class="text-xs text-gray-600">
                                Current Session: <span class="font-semibold text-gray-900">#{{ props.sessionId }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-xs shadow-sm bg-gray-50">
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="props.reverbStatus.ok ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'"
                            />
                            <span class="font-medium text-gray-700">WebSocket</span>
                            <span class="text-gray-500">
                                {{ props.reverbStatus.ok ? 'Connected' : 'Offline' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card v-for="dashCard in dashCards" :key="dashCard.title" class="shadow-sm hover:shadow-md transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between p-4 pb-2">
                        <CardTitle class="text-sm font-medium text-gray-600">
                            {{ dashCard.title }}
                        </CardTitle>
                        <component :is="dashCard.icon" class="text-woosoo-green h-4 w-4" />
                    </CardHeader>
                    <CardContent class="p-4 pt-0">
                        <div class="text-2xl font-bold text-gray-900">
                            {{ dashCard.value }}
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ dashCard.helpText }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Device Monitoring Section -->
            <div v-if="devicesWithTables.length > 0" class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Tablet Ordering Devices</h2>
                        <p class="text-sm text-gray-500 mt-1">{{ devicesWithTables.filter(d => d.is_active).length }} of {{ devicesWithTables.length }} devices online</p>
                    </div>
                    <Tablet class="h-5 w-5 text-gray-400" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                    <Card
                        v-for="device in devicesWithTables"
                        :key="device.id"
                        class="relative overflow-hidden transition-all hover:shadow-md"
                        :class="device.is_active ? 'bg-white border-emerald-200' : 'bg-gray-50 border-gray-200 opacity-80'"
                    >
                        <!-- Status Indicator -->
                        <div
                            class="absolute top-0 left-0 w-1 h-full"
                            :class="device.is_active ? 'bg-emerald-500' : 'bg-gray-300'"
                        />

                        <CardHeader class="p-3 pb-2">
                            <div class="flex items-start justify-between pl-2">
                                <div class="flex-1 min-w-0">
                                    <CardTitle class="text-sm font-semibold text-gray-900 truncate">
                                        {{ device.table?.name || device.name || 'Device ' + device.id }}
                                    </CardTitle>
                                    <CardDescription class="text-xs mt-0.5 flex items-center gap-1">
                                        <span
                                            class="inline-block h-1.5 w-1.5 rounded-full"
                                            :class="device.is_active ? 'bg-emerald-500' : 'bg-gray-400'"
                                        />
                                        {{ device.is_active ? 'Online' : 'Offline' }}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="p-3 pt-0 pl-5 space-y-1.5">
                            <!-- Today's Orders -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-600">Today</span>
                                <span class="text-sm font-bold text-gray-900">{{ device.today_orders_count }}</span>
                            </div>

                            <!-- Pending Orders with Alert -->
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-600">Pending</span>
                                <span
                                    class="text-sm font-bold flex items-center gap-1"
                                    :class="device.pending_orders_count > 0 ? 'text-amber-600' : 'text-gray-900'"
                                >
                                    <span>{{ device.pending_orders_count }}</span>
                                    <AlertCircle v-if="device.pending_orders_count > 0" class="h-3 w-3" />
                                </span>
                            </div>

                            <!-- Last Activity -->
                            <div v-if="device.last_order_at" class="pt-1.5 border-t border-gray-100">
                                <span class="text-xs text-gray-500">
                                    Last: {{ new Date(device.last_order_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' }) }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
