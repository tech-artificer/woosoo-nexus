<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { RefreshCw } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { type BreadcrumbItem } from '@/types';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

const { success: toastSuccess, error: toastError } = useToast();

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
            order_id: number | null;
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
    printLatency: {
        windows: Record<string, {
            total: number;
            acked: number;
            avg_sec: number | null;
            p50_sec: number | null;
            p95_sec: number | null;
            max_sec: number | null;
        }>;
        stuck: number;
        recent: Array<{
            id: number;
            device_order_id: number | null;
            order_number: string | null;
            table_name: string | null;
            device_name: string | null;
            event_type: string;
            pei_count: number;
            created_at: string | null;
            broadcast_at: string | null;
            reserved_at: string | null;
            acknowledged_at: string | null;
            total_sec: number | null;
            is_acknowledged: boolean;
        }>;
    };
    devices: Array<{
        id: number;
        name: string;
        table_id: number | null;
        is_active: boolean;
        last_seen_at: string | null;
        last_seen_secs_ago: number | null;
        state: 'green' | 'yellow' | 'red' | 'unknown';
    }>;
    activeSessions: Array<{
        session_id: number;
        opened_at: string;
        pos_reachable: boolean;
        unpaid_count: number;
        can_safely_force_end: boolean;
        orders: Array<{
            id: number;
            order_id: number | null;
            order_number: string | null;
            status: string;
            is_open: boolean;
            table_name: string | null;
            device_name: string | null;
            guest_count: number | null;
            total: string | number | null;
            payment_state: 'paid' | 'unpaid' | 'voided' | 'unknown' | 'no_pos_link' | 'pos_missing' | 'pos_unreachable';
            created_at: string | null;
        }>;
    }>;
    timestamp: string;
}

const props = defineProps<{
    metrics: MonitoringMetrics;
}>();

const metrics = ref<MonitoringMetrics>(props.metrics);
const loading = ref(false);
const autoRefresh = ref(true);
const refreshInterval = ref<ReturnType<typeof setInterval> | null>(null);

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
        // Skip polling while the tab is hidden — saves a MySQL+POS round-trip
        // every 30s when an admin leaves the tab open in the background.
        if (typeof document !== 'undefined' && document.hidden) return;
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

const retryPrint = async (orderId: number | null) => {
    if (!orderId || loading.value) return;

    loading.value = true;
    try {
        await new Promise<void>((resolve, reject) => {
            router.post(route('orders.print'), { order_id: orderId }, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    toastSuccess('Print job re-queued')
                    resolve()
                },
                onError: () => reject(new Error('Print retry failed')),
            })
        })
        await refreshMetrics();
    } catch (error) {
        toastError('Failed to retry print job')
        console.error('Print retry failed:', error);
    } finally {
        loading.value = false;
    }
};

// Action feedback uses the global vue-sonner toast (useToast composable)
// so it floats over the page instead of being rendered inline per-session.
// Replaces the previous setTimeout-managed inline banner that leaked the
// timer on unmount and rendered duplicated when multiple sessions existed.

const resetSession = async (sessionId: number) => {
    if (loading.value) return;
    if (!confirm(`Reset session ${sessionId}? Tablets will clear local caches but the session stays open in POS.`)) return;

    loading.value = true;
    try {
        const res = await axios.post(`/monitoring/sessions/${sessionId}/reset`);
        const message = res.data?.message ?? 'Reset dispatched.';
        if (res.data?.success) { toastSuccess(message) } else { toastError(message) }
        await refreshMetrics();
    } catch (e: any) {
        toastError(e?.response?.data?.message ?? 'Reset failed — see browser console.');
        console.error('Reset session failed:', e);
    } finally {
        loading.value = false;
    }
};

const forceEndSession = async (sessionId: number, canSafelyForceEnd: boolean, unpaidCount: number) => {
    if (loading.value) return;

    const force = !canSafelyForceEnd;
    const prompt = force
        ? `OVERRIDE: Force-end session ${sessionId} with ${unpaidCount} unpaid order(s)?\n\nThis will void those orders LOCALLY regardless of POS state. The cashier should normally close them in POS first. This action is audit-logged.\n\nType the session ID (${sessionId}) to confirm:`
        : `Force-end session ${sessionId}? All open orders will be closed (matching POS state) and tablets will be reset.`;

    if (force) {
        const typed = window.prompt(prompt);
        if (typed === null) return;
        if (typed.trim() !== String(sessionId)) {
            toastError('Session ID did not match — force-end cancelled.');
            return;
        }
    } else if (!confirm(prompt)) {
        return;
    }

    loading.value = true;
    try {
        const res = await axios.post(`/monitoring/sessions/${sessionId}/force-end`, { force });
        const message = res.data?.message ?? 'Force-end submitted.';
        if (res.data?.success) { toastSuccess(message) } else { toastError(message) }
        await refreshMetrics();
    } catch (e: any) {
        toastError(e?.response?.data?.message ?? 'Force-end failed — see browser console.');
        console.error('Force-end failed:', e);
    } finally {
        loading.value = false;
    }
};

const fmtSecs = (s: number | null): string => {
    if (s === null || s === undefined) return '—';
    if (s < 60) return `${s}s`;
    if (s < 3600) return `${Math.floor(s / 60)}m ${s % 60}s`;
    return `${Math.floor(s / 3600)}h ${Math.floor((s % 3600) / 60)}m`;
};

const fmtTime = (iso: string | null): string => iso ? new Date(iso).toLocaleTimeString() : '—';

const latencyBadgeVariant = (sec: number | null): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (sec === null) return 'outline';
    if (sec <= 5) return 'default';
    if (sec <= 15) return 'secondary';
    return 'destructive';
};

const paymentBadgeVariant = (state: string): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (state === 'paid' || state === 'voided') return 'default';
    if (state === 'unpaid') return 'destructive';
    return 'outline';
};

const deviceStateColor = (state: string): string => {
    if (state === 'green') return 'bg-woosoo-green';
    if (state === 'yellow') return 'bg-woosoo-accent';
    if (state === 'red') return 'bg-destructive';
    return 'bg-muted-foreground';
};

onMounted(() => {
    if (autoRefresh.value) {
        startAutoRefresh();
    }
});

onUnmounted(() => {
    stopAutoRefresh();
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'System Monitoring', href: route('monitoring.index') },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">

        <Head title="System Monitoring" />

        <div class="space-y-5">
            <!-- Header -->
            <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-3">
                        <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            System health
                        </span>
                        <div>
                            <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">System Monitoring</h1>
                            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Real-time order processing and print failure tracking</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="outline" size="sm" as-child>
                            <Link :href="route('reports.print-audit')">Print Audit</Link>
                        </Button>
                        <Button variant="outline" size="sm" @click="toggleAutoRefresh"
                            :class="{ 'bg-woosoo-accent/10 text-woosoo-primary-dark': autoRefresh }">
                            <RefreshCw class="h-4 w-4 mr-2" :class="{ 'animate-spin': autoRefresh }" />
                            {{ autoRefresh ? 'Auto-refresh ON' : 'Auto-refresh OFF' }}
                        </Button>
                        <Button size="sm" @click="refreshMetrics" :disabled="loading">
                            <RefreshCw class="h-4 w-4 mr-2" :class="{ 'animate-spin': loading }" />
                            Refresh Now
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Alert Banner -->
            <div v-if="hasAlerts" class="bg-destructive/10 border border-destructive/20 rounded-[20px] p-4">
                <div class="flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-destructive" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                            clip-rule="evenodd" />
                    </svg>
                    <span class="font-semibold text-destructive">{{ totalAlerts }} active alerts require attention</span>
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
                                    <th class="text-left p-2">Action</th>
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
                                    <td class="p-2">
                                        <Button
                                            v-if="evt.order_id"
                                            variant="outline"
                                            size="sm"
                                            :disabled="loading"
                                            @click="retryPrint(evt.order_id)"
                                        >
                                            Retry
                                        </Button>
                                        <span v-else class="text-xs text-muted-foreground">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Print Latency -->
            <Card>
                <CardHeader>
                    <CardTitle>Print Latency</CardTitle>
                    <CardDescription>Time from order creation to printer acknowledgement (kitchen SLA: &lt;5s)</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div v-for="(stats, label) in metrics.printLatency.windows" :key="label" class="border rounded p-3">
                            <div class="text-xs text-muted-foreground uppercase">Last {{ label }}</div>
                            <div class="text-2xl font-bold">{{ stats.acked }}<span class="text-sm text-muted-foreground"> / {{ stats.total }}</span></div>
                            <div class="text-xs mt-1 space-x-2">
                                <span>p50 <strong>{{ fmtSecs(stats.p50_sec) }}</strong></span>
                                <span>p95 <strong>{{ fmtSecs(stats.p95_sec) }}</strong></span>
                                <span>max <strong>{{ fmtSecs(stats.max_sec) }}</strong></span>
                            </div>
                        </div>
                    </div>

                    <div v-if="metrics.printLatency.stuck > 0" class="mb-4 p-3 rounded-[20px] bg-destructive/10 border border-destructive/20 text-sm">
                        <strong class="text-destructive">{{ metrics.printLatency.stuck }} stuck event(s)</strong>
                        — broadcast went out but never acknowledged (&gt;2 min).
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-2">#</th>
                                    <th class="text-left p-2">Order</th>
                                    <th class="text-left p-2">Type</th>
                                    <th class="text-left p-2">Items</th>
                                    <th class="text-left p-2">Created</th>
                                    <th class="text-left p-2">Broadcast</th>
                                    <th class="text-left p-2">Reserved</th>
                                    <th class="text-left p-2">Acked</th>
                                    <th class="text-left p-2">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="evt in metrics.printLatency.recent" :key="evt.id" class="border-b hover:bg-muted/50">
                                    <td class="p-2 font-mono">{{ evt.id }}</td>
                                    <td class="p-2 font-mono text-xs">{{ evt.order_number || '—' }}</td>
                                    <td class="p-2"><Badge variant="outline">{{ evt.event_type }}</Badge></td>
                                    <td class="p-2">
                                        <Badge :variant="evt.pei_count > 0 ? 'default' : 'destructive'">{{ evt.pei_count }}</Badge>
                                    </td>
                                    <td class="p-2 text-xs">{{ fmtTime(evt.created_at) }}</td>
                                    <td class="p-2 text-xs">{{ fmtTime(evt.broadcast_at) }}</td>
                                    <td class="p-2 text-xs">{{ fmtTime(evt.reserved_at) }}</td>
                                    <td class="p-2 text-xs">{{ fmtTime(evt.acknowledged_at) }}</td>
                                    <td class="p-2">
                                        <Badge :variant="latencyBadgeVariant(evt.total_sec)">{{ fmtSecs(evt.total_sec) }}</Badge>
                                    </td>
                                </tr>
                                <tr v-if="metrics.printLatency.recent.length === 0">
                                    <td colspan="9" class="p-4 text-center text-muted-foreground">No print events yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Bridge Devices -->
            <Card>
                <CardHeader>
                    <CardTitle>Devices</CardTitle>
                    <CardDescription>Registered tablets + print bridges. Green = heartbeat in last 60s.</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-2">State</th>
                                    <th class="text-left p-2">ID</th>
                                    <th class="text-left p-2">Name</th>
                                    <th class="text-left p-2">Table</th>
                                    <th class="text-left p-2">Active</th>
                                    <th class="text-left p-2">Last seen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="d in metrics.devices" :key="d.id" class="border-b hover:bg-muted/50">
                                    <td class="p-2">
                                        <span class="inline-block h-2.5 w-2.5 rounded-full" :class="deviceStateColor(d.state)"></span>
                                    </td>
                                    <td class="p-2 font-mono">{{ d.id }}</td>
                                    <td class="p-2">{{ d.name }}</td>
                                    <td class="p-2">{{ d.table_id ?? '—' }}</td>
                                    <td class="p-2">
                                        <Badge :variant="d.is_active ? 'default' : 'outline'">{{ d.is_active ? 'yes' : 'no' }}</Badge>
                                    </td>
                                    <td class="p-2 text-xs text-muted-foreground">
                                        {{ d.last_seen_secs_ago !== null ? fmtSecs(d.last_seen_secs_ago) + ' ago' : 'never' }}
                                    </td>
                                </tr>
                                <tr v-if="metrics.devices.length === 0">
                                    <td colspan="6" class="p-4 text-center text-muted-foreground">No devices registered.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Active Sessions -->
            <Card v-for="sess in metrics.activeSessions" :key="sess.session_id">
                <CardHeader>
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div>
                            <CardTitle>Active Session #{{ sess.session_id }}</CardTitle>
                            <CardDescription>
                                Opened {{ new Date(sess.opened_at).toLocaleString() }}
                                · POS: <Badge :variant="sess.pos_reachable ? 'default' : 'destructive'" class="ml-1">{{ sess.pos_reachable ? 'reachable' : 'unreachable' }}</Badge>
                                · Unpaid orders: <Badge :variant="sess.unpaid_count > 0 ? 'destructive' : 'default'" class="ml-1">{{ sess.unpaid_count }}</Badge>
                            </CardDescription>
                        </div>
                        <div class="flex gap-2">
                            <Button variant="outline" size="sm" :disabled="loading"
                                @click="resetSession(sess.session_id)">
                                Reset (clear tablet caches)
                            </Button>
                            <Button :variant="sess.can_safely_force_end ? 'default' : 'destructive'" size="sm" :disabled="loading"
                                @click="forceEndSession(sess.session_id, sess.can_safely_force_end, sess.unpaid_count)">
                                {{ sess.can_safely_force_end ? 'Force-end' : 'Force-end (OVERRIDE)' }}
                            </Button>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b">
                                    <th class="text-left p-2">DO ID</th>
                                    <th class="text-left p-2">POS Order</th>
                                    <th class="text-left p-2">Order #</th>
                                    <th class="text-left p-2">Table</th>
                                    <th class="text-left p-2">Device</th>
                                    <th class="text-left p-2">Guests</th>
                                    <th class="text-left p-2">Total</th>
                                    <th class="text-left p-2">Status</th>
                                    <th class="text-left p-2">Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="o in sess.orders" :key="o.id" class="border-b hover:bg-muted/50">
                                    <td class="p-2 font-mono">{{ o.id }}</td>
                                    <td class="p-2 font-mono text-xs">{{ o.order_id ?? '—' }}</td>
                                    <td class="p-2 font-mono text-xs">{{ o.order_number ?? '—' }}</td>
                                    <td class="p-2">{{ o.table_name ?? '—' }}</td>
                                    <td class="p-2">{{ o.device_name ?? '—' }}</td>
                                    <td class="p-2">{{ o.guest_count ?? '—' }}</td>
                                    <td class="p-2 text-right">{{ o.total ?? '—' }}</td>
                                    <td class="p-2">
                                        <Badge :variant="o.is_open ? 'secondary' : 'outline'">{{ o.status }}</Badge>
                                    </td>
                                    <td class="p-2">
                                        <Badge :variant="paymentBadgeVariant(o.payment_state)">{{ o.payment_state }}</Badge>
                                    </td>
                                </tr>
                                <tr v-if="sess.orders.length === 0">
                                    <td colspan="9" class="p-4 text-center text-muted-foreground">No orders in this session.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="metrics.activeSessions.length === 0">
                <CardHeader>
                    <CardTitle>Active Session</CardTitle>
                    <CardDescription>No active POS session right now.</CardDescription>
                </CardHeader>
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
    </AppLayout>
</template>
