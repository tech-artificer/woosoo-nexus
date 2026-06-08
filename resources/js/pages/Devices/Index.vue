<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import {
    MonitorSmartphone, Wifi, WifiOff, AlertTriangle, Battery, BatteryLow,
    BatteryMedium, RotateCcw, Plus, Eye, Download, RefreshCw, ShieldCheck,
    ShieldAlert, Lock,
} from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import DeviceDetailSheet from '@/components/Devices/DeviceDetailSheet.vue'
import { type BreadcrumbItem } from '@/types'
import type { Device } from '@/types/models'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'

interface FleetStats {
    online_count: number
    warning_count: number
    offline_count: number
    avg_battery: number | null
    modal_app_version: string | null
}

const props = defineProps<{
    title: string
    description: string
    devices: Device[]
    stats?: any[]
    fleetStats?: FleetStats
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Devices', href: route('devices.index') },
]

const page = usePage()
const revealedSecurityCode = computed(() => String((page.props as any)?.flash?.security_code_reveal || ''))
const showSecurityCodeReveal = ref(Boolean(revealedSecurityCode.value))

const selectedDevice = ref<Device | null>(null)
const isDeviceDetailOpen = ref(false)
const restartTarget = ref<Device | null>(null)
const isRestarting = ref<number | null>(null)
const isSyncingAll = ref(false)

const activeDevices = computed(() => props.devices.filter((d) => !d.deleted_at))

function deviceStatus(device: Device): 'online' | 'warning' | 'offline' {
    if (device.deleted_at) return 'offline'
    const s = (device.status ?? '').toLowerCase()
    if (s === 'online') return 'online'
    if (s === 'warning') return 'warning'
    if (s === 'offline') return 'offline'
    // Derive from heartbeat age when status field is not set
    if (!device.last_seen_at && !device.last_heartbeat_at) return 'offline'
    const lastSeen = new Date(device.last_heartbeat_at ?? device.last_seen_at ?? 0).getTime()
    const diffMin = (Date.now() - lastSeen) / 60000
    if (diffMin > 30) return 'offline'
    if (diffMin > 5) return 'warning'
    return 'online'
}

function lastPingLabel(device: Device): string {
    const ts = device.last_heartbeat_at ?? device.last_seen_at
    if (!ts) return '—'
    const diffSec = Math.floor((Date.now() - new Date(ts).getTime()) / 1000)
    if (diffSec < 60) return `${diffSec}s ago`
    const diffMin = Math.floor(diffSec / 60)
    if (diffMin < 60) return `${diffMin}m ago`
    return `${Math.floor(diffMin / 60)}h ago`
}

function batteryLevel(device: Device): number | null {
    const b = device.latest_heartbeat?.battery_level
    return b != null ? Math.round(Number(b)) : null
}

function batteryColor(pct: number | null): string {
    if (pct == null) return 'text-muted-foreground'
    if (pct >= 60) return 'text-woosoo-green'
    if (pct >= 15) return 'text-[#f6b56d]'
    return 'text-woosoo-red'
}

function batteryBg(pct: number | null): string {
    if (pct == null) return 'bg-muted'
    if (pct >= 60) return 'bg-woosoo-green'
    if (pct >= 15) return 'bg-[#f6b56d]'
    return 'bg-woosoo-red'
}

function isSecurityExpired(device: Device): boolean {
    if (!device.app_version) return false
    const modal = props.fleetStats?.modal_app_version
    if (!modal || !device.app_version) return false
    return device.app_version !== modal && deviceStatus(device) !== 'online'
}

function openDeviceDetail(device: Device) {
    selectedDevice.value = device
    isDeviceDetailOpen.value = true
}

function confirmRestart(device: Device) {
    restartTarget.value = device
}

function executeRestart() {
    if (!restartTarget.value) return
    const device = restartTarget.value
    restartTarget.value = null
    isRestarting.value = device.id
    router.post(route('devices.security-code.regenerate', device.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(`${device.name} restarted.`)
        },
        onError: () => toast.error('Restart failed.'),
        onFinish: () => { isRestarting.value = null },
    })
}

function syncAll() {
    isSyncingAll.value = true
    router.reload({
        onSuccess: () => toast.success('Fleet synced.'),
        onFinish: () => { isSyncingAll.value = false },
    })
}
</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero header -->
            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#f6b56d]/10 via-transparent to-transparent dark:from-[#f6b56d]/6" />
                <div class="relative flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="space-y-2">
                        <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            Tablet Management
                        </span>
                        <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Devices
                        </h2>
                        <!-- Fleet status summary pills -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-woosoo-green/30 bg-woosoo-green/10 px-2.5 py-1 text-xs font-medium text-woosoo-green">
                                <span class="h-1.5 w-1.5 rounded-full bg-woosoo-green" />
                                {{ fleetStats?.online_count ?? 0 }} online
                            </span>
                            <span v-if="(fleetStats?.warning_count ?? 0) > 0" class="inline-flex items-center gap-1.5 rounded-full border border-[#f6b56d]/30 bg-[#f6b56d]/10 px-2.5 py-1 text-xs font-medium text-[#f6b56d]">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#f6b56d]" />
                                {{ fleetStats?.warning_count }} warning
                            </span>
                            <span v-if="(fleetStats?.offline_count ?? 0) > 0" class="inline-flex items-center gap-1.5 rounded-full border border-woosoo-red/30 bg-woosoo-red/10 px-2.5 py-1 text-xs font-medium text-woosoo-red">
                                <span class="h-1.5 w-1.5 rounded-full bg-woosoo-red" />
                                {{ fleetStats?.offline_count }} offline
                            </span>
                        </div>
                    </div>

                    <!-- KPI strip -->
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:w-auto">
                        <div class="rounded-[18px] border border-black/8 bg-white/72 px-4 py-3 dark:border-white/10 dark:bg-white/[0.06]">
                            <p class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Devices</p>
                            <p class="mt-1 font-mono text-xl font-semibold tabular-nums">{{ activeDevices.length }}</p>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/72 px-4 py-3 dark:border-white/10 dark:bg-white/[0.06]">
                            <p class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Avg Battery</p>
                            <p class="mt-1 font-mono text-xl font-semibold tabular-nums" :class="batteryColor(fleetStats?.avg_battery ?? null)">
                                {{ fleetStats?.avg_battery != null ? `${fleetStats.avg_battery}%` : '—' }}
                            </p>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/72 px-4 py-3 dark:border-white/10 dark:bg-white/[0.06]">
                            <p class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">App Version</p>
                            <p class="mt-1 font-mono text-xl font-semibold tabular-nums">
                                {{ fleetStats?.modal_app_version ?? '—' }}
                            </p>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/72 px-4 py-3 dark:border-white/10 dark:bg-white/[0.06]">
                            <p class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Network</p>
                            <p class="mt-1 font-mono text-xl font-semibold tabular-nums text-woosoo-green">LAN ✓</p>
                        </div>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="relative mt-4 flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" :disabled="isSyncingAll" @click="syncAll">
                        <RefreshCw class="mr-1.5 h-3.5 w-3.5" :class="{ 'animate-spin': isSyncingAll }" />
                        Sync All
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <a :href="route('devices.download-apk')">
                            <Download class="mr-1.5 h-3.5 w-3.5" />
                            APK Download
                        </a>
                    </Button>
                    <Button size="sm" as-child>
                        <Link :href="route('devices.create')">
                            <Plus class="mr-1.5 h-3.5 w-3.5" />
                            Add Device
                        </Link>
                    </Button>
                </div>
            </section>

            <!-- Device card grid -->
            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="p-4 sm:p-6">
                    <div v-if="activeDevices.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                        No devices registered yet.
                        <Link :href="route('devices.create')" class="ml-1 underline">Register the first device.</Link>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <div
                            v-for="device in activeDevices"
                            :key="device.id"
                            class="group relative flex flex-col gap-3 rounded-[18px] border border-black/8 bg-white/60 p-4 transition-all duration-150 hover:border-white/20 hover:shadow-sm dark:border-white/10 dark:bg-white/[0.04]"
                            :class="{
                                'border-woosoo-red/30 dark:border-woosoo-red/20': deviceStatus(device) === 'offline',
                                'border-[#f6b56d]/30 dark:border-[#f6b56d]/20': deviceStatus(device) === 'warning',
                            }"
                        >
                            <!-- Card header: icon + name + status -->
                            <div class="flex items-start gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                                    :class="{
                                        'bg-woosoo-green/10': deviceStatus(device) === 'online',
                                        'bg-[#f6b56d]/10': deviceStatus(device) === 'warning',
                                        'bg-woosoo-red/10': deviceStatus(device) === 'offline',
                                    }"
                                >
                                    <MonitorSmartphone
                                        class="h-4 w-4"
                                        :class="{
                                            'text-woosoo-green': deviceStatus(device) === 'online',
                                            'text-[#f6b56d]': deviceStatus(device) === 'warning',
                                            'text-woosoo-red': deviceStatus(device) === 'offline',
                                        }"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ device.name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-muted-foreground">
                                        {{ device.branch?.name ?? 'No branch' }}
                                        <span v-if="device.table?.name"> · {{ device.table.name }}</span>
                                    </p>
                                </div>
                                <!-- Status pill -->
                                <span
                                    class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase"
                                    :class="{
                                        'bg-woosoo-green/10 text-woosoo-green': deviceStatus(device) === 'online',
                                        'bg-[#f6b56d]/10 text-[#f6b56d]': deviceStatus(device) === 'warning',
                                        'bg-woosoo-red/10 text-woosoo-red': deviceStatus(device) === 'offline',
                                    }"
                                >
                                    <span class="h-1 w-1 rounded-full"
                                        :class="{
                                            'bg-woosoo-green': deviceStatus(device) === 'online',
                                            'bg-[#f6b56d]': deviceStatus(device) === 'warning',
                                            'bg-woosoo-red': deviceStatus(device) === 'offline',
                                        }"
                                    />
                                    {{ deviceStatus(device) }}
                                </span>
                            </div>

                            <!-- Data grid -->
                            <div class="grid grid-cols-2 gap-x-3 gap-y-2 rounded-lg bg-black/[0.03] p-3 dark:bg-white/[0.03]">
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Table</p>
                                    <p class="mt-0.5 font-mono text-xs font-medium">{{ device.table?.name ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Last Ping</p>
                                    <p class="mt-0.5 font-mono text-xs font-medium">{{ lastPingLabel(device) }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">App Ver</p>
                                    <p class="mt-0.5 font-mono text-xs font-medium">{{ device.app_version ?? device.latest_heartbeat?.app_version ?? '—' }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">IP</p>
                                    <p class="mt-0.5 font-mono text-xs font-medium">{{ device.ip_address ?? '—' }}</p>
                                </div>
                            </div>

                            <!-- Battery bar -->
                            <div>
                                <div class="flex items-center justify-between">
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Battery</p>
                                    <p class="font-mono text-xs font-medium" :class="batteryColor(batteryLevel(device))">
                                        {{ batteryLevel(device) != null ? `${batteryLevel(device)}%` : '—' }}
                                    </p>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-black/10 dark:bg-white/10">
                                    <div
                                        v-if="batteryLevel(device) != null"
                                        class="h-full rounded-full transition-all"
                                        :class="batteryBg(batteryLevel(device))"
                                        :style="{ width: `${batteryLevel(device)}%` }"
                                    />
                                </div>
                                <p v-if="deviceStatus(device) === 'offline' && (batteryLevel(device) ?? 100) < 5" class="mt-1 text-[10px] font-medium text-woosoo-red">
                                    ⚠ Battery depleted
                                </p>
                            </div>

                            <!-- Footer: security + actions -->
                            <div class="flex items-center justify-between">
                                <span
                                    class="inline-flex items-center gap-1 text-[10px] font-medium"
                                    :class="isSecurityExpired(device) ? 'text-woosoo-red' : 'text-woosoo-green'"
                                >
                                    <component :is="isSecurityExpired(device) ? ShieldAlert : ShieldCheck" class="h-3 w-3" />
                                    {{ isSecurityExpired(device) ? 'Expired' : 'Sec OK' }}
                                </span>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openDeviceDetail(device)">
                                        <Eye class="mr-1 h-3 w-3" />
                                        View
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        :disabled="isRestarting === device.id"
                                        @click="confirmRestart(device)"
                                    >
                                        <RotateCcw class="mr-1 h-3 w-3" :class="{ 'animate-spin': isRestarting === device.id }" />
                                        Restart
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <DeviceDetailSheet v-model:open="isDeviceDetailOpen" :device="selectedDevice" />

        <!-- Restart confirm -->
        <AlertDialog :open="!!restartTarget" @update:open="(v) => { if (!v) restartTarget = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Restart {{ restartTarget?.name }}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will send a restart signal to the device. The tablet will briefly go offline.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="executeRestart">Restart</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Security code reveal -->
        <Dialog v-model:open="showSecurityCodeReveal">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Security Code Created</DialogTitle>
                    <DialogDescription>
                        This code is shown once. Save it now for tablet registration.
                    </DialogDescription>
                </DialogHeader>
                <div class="rounded-md bg-muted p-4 text-center font-mono text-xl tracking-widest">
                    {{ revealedSecurityCode }}
                </div>
                <DialogFooter>
                    <Button type="button" @click="showSecurityCodeReveal = false">Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
