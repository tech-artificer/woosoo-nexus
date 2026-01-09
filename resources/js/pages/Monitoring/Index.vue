<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import axios from 'axios';

interface MonitoringMetrics {
    unprintedOrders: {
        count: number;
        items: Array<{
            id: number;
            order_number: string;
            device_name: string;
            table_name: string;
            status: string;
            created_at: string;
            minutes_ago: number;
        }>;
    };
    failedPrintEvents: {
        count: number;
        items: Array<{
            id: number;
            device_order_id: number;
            order_number: string;
            device_name: string;
            table_name: string;
            event_type: string;
            attempts: number;
            last_error: string | null;
            created_at: string;
        }>;
    };
    queue: {
        pending: number;
        failed: number;
    };
    refillErrors: number;
    orphanedOrders: number;
    database: {
        mysql: boolean;
        pos: boolean;
    };
    timestamp: string;
}

const props = defineProps<{
    metrics: MonitoringMetrics;
}>();

const metrics = ref<MonitoringMetrics>(props.metrics);
const loading = ref(false);
const autoRefresh = ref(true);
const refreshInterval = ref<NodeJS.Timeout | null>(null);

const totalAlerts = computed(() => {
    return (
        metrics.value.unprintedOrders.count +
        metrics.value.failedPrintEvents.count +
        (metrics.value.queue.failed > 0 ? 1 : 0) +
        (metrics.value.refillErrors > 0 ? 1 : 0) +
        (metrics.value.orphanedOrders > 0 ? 1 : 0) +
        (!metrics.value.database.mysql ? 1 : 0) +
        (!metrics.value.database.pos ? 1 : 0)
    );
});

const hasAlerts = computed(() => totalAlerts.value > 0);

const refreshMetrics = async () => {
    if (loading.value) return;

    loading.value = true;
    try {
        const response = await axios.get('/monitoring/metrics');
        metrics.value = response.data;
    } catch (error) {
        console.error('Failed to refresh metrics:', error);
    } finally {
        loading.value = false;
    }
};

const toggleAutoRefresh = () => {
    autoRefresh.value = !autoRefresh.value;
    if (autoRefresh.value) {
        startAutoRefresh();
    } else {
        stopAutoRefresh();
    }
};

const startAutoRefresh = () => {
    if (refreshInterval.value) return;
    refreshInterval.value = setInterval(() => {
        refreshMetrics();
    }, 30000); // 30 seconds
};

const stopAutoRefresh = () => {
    if (refreshInterval.value) {
        clearInterval(refreshInterval.value);
        refreshInterval.value = null;
    }
};

const purgePrintEvents = async () => {
    if (loading.value) return;

    loading.value = true;
    try {
        await axios.post('/monitoring/purge-print-events');
        await refreshMetrics();
    } catch (error) {
        console.error('Failed to purge print events:', error);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    if (autoRefresh.value) {
        startAutoRefresh();
    }
});

onUnmounted(() => {
    stopAutoRefresh();
});

defineOptions({ layout: AppLayout });
</script>

<template>
    <div>

        <Head title="System Monitoring" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">System Monitoring</h1>
                    <p class="text-muted-foreground">Real-time order processing and print failure tracking</p>
                </div>
                <div class="flex items-center gap-3">
                    <Button variant="outline" size="sm" @click="toggleAutoRefresh"
                        :class="{ 'bg-primary/10': autoRefresh }">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2"
                            :class="{ 'animate-spin': autoRefresh }" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        {{ autoRefresh ? 'Auto-refresh ON' : 'Auto-refresh OFF' }}
                    </Button>
                    <Button size="sm" @click="refreshMetrics" :disabled="loading">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2"
                            :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh Now
                    </Button>
                </div>
            </div>

            <!-- Alert Banner -->
            <div v-if="hasAlerts" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <span class="font-semibold text-red-900">{{ totalAlerts }} active alerts require attention</span>
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <!-- Unprinted Orders -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Unprinted Orders</CardTitle>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                        </svg>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ metrics.unprintedOrders.count }}</div>
                        <p class="text-xs text-muted-foreground">Orders pending print >10min</p>
                    </CardContent>
                </Card>

                <!-- Failed Print Events -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Failed Prints</CardTitle>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ metrics.failedPrintEvents.count }}</div>
                        <p class="text-xs text-muted-foreground">Print events with >3 attempts</p>
                    </CardContent>
                </Card>

                <!-- Queue Status -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Queue Status</CardTitle>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ metrics.queue.pending }}
                            <span v-if="metrics.queue.failed > 0" class="text-red-600">({{ metrics.queue.failed
                            }})</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Pending jobs / failed</p>
                    </CardContent>
                </Card>

                <!-- Database Health -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Database Health</CardTitle>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted-foreground" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4" />
                        </svg>
                    </CardHeader>
                    <CardContent>
                        <div class="flex items-center gap-2">
                            <Badge :variant="metrics.database.mysql ? 'default' : 'destructive'">MySQL</Badge>
                            <Badge :variant="metrics.database.pos ? 'default' : 'destructive'">POS</Badge>
                        </div>
                        <p class="text-xs text-muted-foreground mt-2">Connection status</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Unprinted Orders Table -->
            <Card v-if="metrics.unprintedOrders.count > 0">
                <CardHeader>
                    <CardTitle>Unprinted Orders</CardTitle>
                    <CardDescription>Orders not marked as printed after 10 minutes</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-2">Order #</th>
                                    <th class="text-left p-2">Device</th>
                                    <th class="text-left p-2">Table</th>
                                    <th class="text-left p-2">Status</th>
                                    <th class="text-left p-2">Age</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="order in metrics.unprintedOrders.items" :key="order.id"
                                    class="border-b hover:bg-muted/50">
                                    <td class="p-2 font-mono">{{ order.order_number }}</td>
                                    <td class="p-2">{{ order.device_name }}</td>
                                    <td class="p-2">{{ order.table_name }}</td>
                                    <td class="p-2">
                                        <Badge variant="outline">{{ order.status }}</Badge>
                                    </td>
                                    <td class="p-2 text-muted-foreground">{{ order.minutes_ago }}m ago</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Failed Print Events Table -->
            <Card v-if="metrics.failedPrintEvents.count > 0">
                <CardHeader>
                    <CardTitle>Failed Print Events</CardTitle>
                    <CardDescription>Print events with repeated failures (>3 attempts)</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-2">Order #</th>
                                    <th class="text-left p-2">Device</th>
                                    <th class="text-left p-2">Table</th>
                                    <th class="text-left p-2">Type</th>
                                    <th class="text-left p-2">Attempts</th>
                                    <th class="text-left p-2">Last Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="evt in metrics.failedPrintEvents.items" :key="evt.id"
                                    class="border-b hover:bg-muted/50">
                                    <td class="p-2 font-mono">{{ evt.order_number }}</td>
                                    <td class="p-2">{{ evt.device_name }}</td>
                                    <td class="p-2">{{ evt.table_name }}</td>
                                    <td class="p-2">
                                        <Badge variant="outline">{{ evt.event_type }}</Badge>
                                    </td>
                                    <td class="p-2">
                                        <Badge variant="destructive">{{ evt.attempts }}</Badge>
                                    </td>
                                    <td class="p-2 text-xs text-muted-foreground truncate max-w-xs">
                                        {{ evt.last_error || 'N/A' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Maintenance Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>Maintenance</CardTitle>
                    <CardDescription>Cleanup and maintenance operations</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-4">
                        <Button variant="outline" @click="purgePrintEvents" :disabled="loading">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            Purge Acknowledged Print Events
                        </Button>
                        <span class="text-sm text-muted-foreground">Remove acknowledged events older than 24
                            hours</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Last Updated -->
            <div class="text-xs text-muted-foreground text-right">
                Last updated: {{ new Date(metrics.timestamp).toLocaleString() }}
            </div>
        </div>
    </div>
</template>
