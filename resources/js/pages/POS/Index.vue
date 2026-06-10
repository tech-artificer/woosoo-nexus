<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { MonitorSmartphone, Circle, ReceiptText, RefreshCw } from 'lucide-vue-next'
import EditOrderDialog from '@/components/pos/EditOrderDialog.vue'
import PaymentDialog from '@/components/pos/PaymentDialog.vue'
import PosTableCard from '@/components/pos/PosTableCard.vue'
import type { PosTerminal, PosTable, PosOrder } from '@/types/pos'
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
    Skeleton,
} from '@/components/ui/skeleton'

const props = defineProps<{
    title: string
    description: string
    terminals: PosTerminal[]
    tables: PosTable[]
    currentSession: {
        id: string
        date_time_opened: string
        date_time_closed: string | null
    } | null
    posConnected?: boolean
    posStatus?: 'connected' | 'not_configured' | 'auth_failed' | 'unreachable'
    posMessage?: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'POS',
        href: route('pos.index'),
    },
]

const pageTitle = computed(() => props.title || 'POS')

const posBannerTitle = computed(() => {
    switch (props.posStatus) {
        case 'not_configured':
            return 'POS password not configured'
        case 'auth_failed':
            return 'POS credentials rejected'
        case 'unreachable':
            return 'POS not reachable'
        default:
            return 'POS not connected'
    }
})
const terminals = computed<PosTerminal[]>(() => (Array.isArray(props.terminals) ? props.terminals : []))

const selectedTerminalId = ref<string | null>(terminals.value[0]?.id ?? null)
const terminalTables = ref<PosTable[]>(Array.isArray(props.tables) ? props.tables : [])
const tablesLoading = ref(false)
const tablesError = ref<string | null>(null)
const loadingTerminalId = ref<string | null>(null)
let tablesLoadSeq = 0

const showOrdersModal = ref(false)
const selectedTable = ref<PosTable | null>(null)
const selectedOrders = ref<PosOrder[]>([])
const ordersLoading = ref(false)
const ordersError = ref<string | null>(null)
const addOrderError = ref<string | null>(null)
const actionLoading = ref(false)

const form = useForm({
    guest_count: 2,
    reference: '',
})

// Dialog state
const editDialogOpen = ref(false)
const paymentDialogOpen = ref(false)
const voidDialogOpen = ref(false)
const selectedOrderForEdit = ref<PosOrder | null>(null)
const selectedOrderForPay = ref<PosOrder | null>(null)
const selectedOrderForVoid = ref<PosOrder | null>(null)

const totalTerminals = computed(() => terminals.value.length)
const activeTerminals = computed(() => terminals.value.filter((terminal) => Boolean(Number(terminal.is_active))).length)
const totalOpenOrders = computed(() =>
    terminals.value.reduce((total, terminal) => total + Number(terminal.open_orders_count || 0), 0)
)

const selectedTerminal = computed(() =>
    terminals.value.find((terminal) => String(terminal.id) === String(selectedTerminalId.value)) ?? null
)

const occupiedTables = computed(() => terminalTables.value.filter((table) => Boolean(Number(table.is_occupied))).length)

const currentSessionStatus = computed(() => {
    if (!props.currentSession) {
        return 'No Session'
    }

    return props.currentSession.date_time_closed ? 'Closed' : 'Open'
})

const readJsonPayload = async (response: Response): Promise<any> => {
    const contentType = response.headers.get('content-type') || ''

    if (contentType.includes('application/json')) {
        return await response.json()
    }

    const text = await response.text()
    return {
        success: false,
        message: text?.slice(0, 200) || 'Unexpected non-JSON response from server.',
    }
}

const formatMoney = (value: number | string): string => {
    const numeric = Number(value || 0)
    return new Intl.NumberFormat('en-PH', {
        style: 'currency',
        currency: 'PHP',
        minimumFractionDigits: 2,
    }).format(Number.isFinite(numeric) ? numeric : 0)
}

const formatDateTime = (value: string | null | undefined): string => {
    if (!value) {
        return '—'
    }

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) {
        return value
    }

    return date.toLocaleString('en-PH', {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

/**
 * Read the XSRF-TOKEN cookie value (set by Laravel, readable by JS).
 * This is the encrypted token — must be sent as X-XSRF-TOKEN so Laravel
 * can decrypt and compare it against the session. Do NOT send it as
 * X-CSRF-TOKEN (that header expects the raw unencrypted token from <meta>).
 */
const getXsrfCookie = (): string => {
    const cookie = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))
    return cookie ? decodeURIComponent(cookie.split('=')[1]) : ''
}

/**
 * Always refreshes the CSRF cookie before sending the request.
 * Handles long-lived POS sessions where the XSRF token has expired.
 */
const fetchWithCsrf = async (url: string, options: RequestInit): Promise<Response> => {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' })

    return fetch(url, {
        ...options,
        credentials: 'include',
        headers: {
            ...(options.headers as Record<string, string>),
            'X-XSRF-TOKEN': getXsrfCookie(),
        },
    })
}

const loadTablesForTerminal = async (terminalId: string) => {
    const seq = ++tablesLoadSeq
    selectedTerminalId.value = terminalId
    loadingTerminalId.value = terminalId
    tablesLoading.value = true
    tablesError.value = null

    try {
        const response = await fetch(route('pos.terminal.tables', { terminalId }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        if (seq !== tablesLoadSeq) return

        const payload = await readJsonPayload(response)

        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to load tables for selected terminal.')
        }

        terminalTables.value = Array.isArray(payload.tables) ? payload.tables : []
    } catch (error: any) {
        if (seq === tablesLoadSeq) {
            tablesError.value = error?.message || 'Unable to load tables from Krypton.'
        }
    } finally {
        if (seq === tablesLoadSeq) {
            tablesLoading.value = false
            loadingTerminalId.value = null
        }
    }
}

const openTableOrders = async (table: PosTable) => {
    if (!selectedTerminalId.value) {
        return
    }

    selectedTable.value = table
    selectedOrders.value = []
    ordersError.value = null
    addOrderError.value = null
    ordersLoading.value = true
    showOrdersModal.value = true

    try {
        const response = await fetch(route('pos.table.orders', { terminalId: selectedTerminalId.value, tableId: table.id }), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        const payload = await readJsonPayload(response)

        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to load Krypton orders for this table.')
        }

        selectedOrders.value = Array.isArray(payload.orders) ? payload.orders : []
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to fetch table orders from Krypton.'
    } finally {
        ordersLoading.value = false
    }
}

const refreshCurrentTableOrders = async () => {
    if (selectedTable.value) {
        await openTableOrders(selectedTable.value)
    }
}

const addOrderForTable = () => {
    if (!selectedTerminalId.value || !selectedTable.value) {
        return
    }

    form.post(route('pos.table.orders.add', { terminalId: selectedTerminalId.value, tableId: selectedTable.value.id }), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('reference')
            refreshCurrentTableOrders()
            loadTablesForTerminal(selectedTerminalId.value!)
        },
        onError: (errors) => {
            addOrderError.value = errors.message || 'Unable to add order from POS.'
        },
    })
}

const editOrder = async (order: PosOrder) => {
    selectedOrderForEdit.value = order
    editDialogOpen.value = true
}

const handleEditSave = async (guestCount: number, reference: string | null) => {
    if (!selectedOrderForEdit.value) return

    actionLoading.value = true
    try {
        const response = await fetchWithCsrf(route('pos.orders.edit', { orderId: selectedOrderForEdit.value.id }), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                guest_count: guestCount,
                reference: reference,
            }),
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to edit order.')
        }

        editDialogOpen.value = false
        await refreshCurrentTableOrders()
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to edit order in Krypton.'
    } finally {
        actionLoading.value = false
    }
}

const voidOrder = (order: PosOrder) => {
    selectedOrderForVoid.value = order
    voidDialogOpen.value = true
}

const handleVoidConfirm = async () => {
    if (!selectedOrderForVoid.value) return

    actionLoading.value = true
    try {
        const response = await fetchWithCsrf(route('pos.orders.void', { orderId: selectedOrderForVoid.value.id }), {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to void order.')
        }

        voidDialogOpen.value = false
        await refreshCurrentTableOrders()
        if (selectedTerminalId.value) {
            await loadTablesForTerminal(selectedTerminalId.value)
        }
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to void order in Krypton.'
    } finally {
        actionLoading.value = false
    }
}

const payOrder = async (order: PosOrder) => {
    selectedOrderForPay.value = order
    paymentDialogOpen.value = true
}

const handlePay = async (amount: number, paymentTypeId: number, tip?: number) => {
    if (!selectedOrderForPay.value) return

    actionLoading.value = true
    try {
        const response = await fetchWithCsrf(route('pos.orders.pay', { orderId: selectedOrderForPay.value.id }), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: JSON.stringify({
                amount,
                payment_type_id: paymentTypeId,
                tip,
            }),
        })

        const payload = await readJsonPayload(response)
        if (!response.ok || !payload?.success) {
            throw new Error(payload?.message || 'Failed to pay order.')
        }

        paymentDialogOpen.value = false
        await refreshCurrentTableOrders()
        if (selectedTerminalId.value) {
            await loadTablesForTerminal(selectedTerminalId.value)
        }
    } catch (error: any) {
        ordersError.value = error?.message || 'Unable to pay order in Krypton.'
    } finally {
        actionLoading.value = false
    }
}
</script>

<template>
    <Head :title="pageTitle" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <div v-if="posConnected === false" class="rounded-[18px] border border-destructive/40 bg-destructive/10 px-5 py-4 text-sm text-destructive">
                <p class="font-semibold">{{ posBannerTitle }}</p>
                <p class="mt-1">{{ posMessage }}</p>
                <a :href="route('pos-connection.index')" class="mt-2 inline-block underline underline-offset-2 hover:opacity-80">Go to Configuration → POS Connection</a>
            </div>

            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-3xl space-y-3">
                        <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            Live table view
                        </span>
                        <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            POS
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground sm:text-base">
                            Dedicated Krypton POS surface. Terminals, tables, orders, and session state in this page are loaded from
                            <span class="font-semibold text-foreground">krypton_woosoo only</span>.
                            Restaurant tables shown here are Krypton's real live tables.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-woosoo-green/30 bg-woosoo-green/10 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-woosoo-green">
                        Data Source: Krypton Only
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-4">
                <div class="rounded-[18px] border border-black/8 bg-white/72 px-5 py-4 shadow-sm dark:border-white/10 dark:bg-white/[0.06]">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Terminals</p>
                    <p class="mt-1 text-2xl font-semibold">{{ totalTerminals }}</p>
                </div>
                <div class="rounded-[18px] border border-black/8 bg-white/72 px-5 py-4 shadow-sm dark:border-white/10 dark:bg-white/[0.06]">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Active Terminals</p>
                    <p class="mt-1 text-2xl font-semibold">{{ activeTerminals }}</p>
                </div>
                <div class="rounded-[18px] border border-black/8 bg-white/72 px-5 py-4 shadow-sm dark:border-white/10 dark:bg-white/[0.06]">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Open Orders</p>
                    <p class="mt-1 text-2xl font-semibold">{{ totalOpenOrders }}</p>
                </div>
                <div class="rounded-[18px] border border-black/8 bg-white/72 px-5 py-4 shadow-sm dark:border-white/10 dark:bg-white/[0.06]">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Current Session</p>
                    <p class="mt-1 text-lg font-semibold">#{{ props.currentSession?.id || '—' }} • {{ currentSessionStatus }}</p>
                    <p class="text-xs text-muted-foreground">Opened: {{ formatDateTime(props.currentSession?.date_time_opened) }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 p-4 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 sm:p-5 lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">POS &gt; Terminal</h3>
                    <p class="text-xs text-muted-foreground">Pick a terminal, then click a table to manage orders.</p>
                </div>
                <div class="p-4 sm:p-5 lg:p-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <button
                            v-for="terminal in terminals"
                            :key="terminal.id"
                            type="button"
                            class="group rounded-[22px] border border-black/8 bg-gradient-to-b from-slate-900/95 to-slate-950 p-4 text-left shadow-lg transition-all hover:-translate-y-0.5 hover:border-woosoo-accent/70 hover:shadow-woosoo-accent/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10"
                            :class="String(selectedTerminalId) === String(terminal.id) ? 'ring-2 ring-woosoo-accent/70' : ''"
                            :disabled="tablesLoading && loadingTerminalId === String(terminal.id)"
                            @click="loadTablesForTerminal(String(terminal.id))"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <span class="inline-flex items-center gap-1 rounded-full border border-white/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white/80">
                                    <Circle :size="8" :class="Number(terminal.is_active) ? 'fill-woosoo-green text-woosoo-green' : 'fill-destructive text-destructive'" />
                                    {{ Number(terminal.is_active) ? 'Active' : 'Inactive' }}
                                </span>
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-white/60">ID {{ terminal.id }}</span>
                            </div>

                            <div class="mx-auto mb-4 flex h-44 w-full max-w-[220px] items-center justify-center rounded-[1.7rem] border-[10px] border-slate-700 bg-slate-800 shadow-inner">
                                <div class="flex h-full w-full flex-col items-center justify-center rounded-[1.2rem] bg-slate-900 text-center text-white/90">
                                    <template v-if="tablesLoading && loadingTerminalId === String(terminal.id)">
                                        <RefreshCw class="mb-2 h-10 w-10 animate-spin text-woosoo-accent" />
                                        <p class="px-2 text-[11px] text-white/50">Loading tables…</p>
                                    </template>
                                    <template v-else>
                                        <MonitorSmartphone class="mb-2 h-10 w-10 text-woosoo-accent" />
                                        <p class="px-2 text-sm font-semibold leading-tight">{{ terminal.name }}</p>
                                        <p class="mt-1 text-[11px] text-white/50">{{ terminal.type }}</p>
                                    </template>
                                </div>
                            </div>

                            <div class="space-y-1 text-xs text-white/70">
                                <p><span class="text-white/45">IP:</span> {{ terminal.ip_address || '—' }}</p>
                                <p><span class="text-white/45">Port:</span> {{ terminal.port ?? '—' }}</p>
                                <p><span class="text-white/45">Session:</span> #{{ terminal.session_id || '—' }} • {{ terminal.session_closed_at ? 'Closed' : 'Open' }}</p>
                                <p><span class="text-white/45">Open Orders:</span> <span class="font-semibold text-woosoo-accent">{{ Number(terminal.open_orders_count) }}</span></p>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Tables Section -->
            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="border-b border-black/8 px-4 py-4 dark:border-white/10 sm:px-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold">POS &gt; Terminal &gt; Tables</h3>
                            <p class="text-xs text-muted-foreground">
                                Selected terminal: <span class="font-semibold text-foreground">{{ selectedTerminal?.name || '—' }}</span>
                            </p>
                        </div>
                        <div class="text-right text-xs text-muted-foreground">
                            <p>Registered Tables: <span class="font-semibold text-foreground">{{ terminalTables.length }}</span></p>
                            <p>Occupied Tables: <span class="font-semibold text-destructive">{{ occupiedTables }}</span></p>
                        </div>
                    </div>
                </div>

                <div class="p-4 sm:p-5">
                    <div v-if="tablesLoading" class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
                        <div v-for="n in 6" :key="n" class="rounded-2xl border p-4 space-y-3">
                            <Skeleton class="h-4 w-1/3 rounded" />
                            <Skeleton class="h-6 w-1/2 rounded" />
                            <Skeleton class="h-4 w-full rounded" />
                        </div>
                    </div>

                    <div v-else-if="tablesError" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {{ tablesError }}
                    </div>

                    <div v-else-if="terminalTables.length === 0" class="rounded-xl border border-black/8 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground dark:border-white/10">
                        No tables found for this terminal.
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-6">
                        <PosTableCard
                            v-for="table in terminalTables"
                            :key="table.id"
                            :table="table"
                            :selected="selectedTable?.id === table.id"
                            @select="openTableOrders(table)"
                        />
                    </div>
                </div>
            </section>

            <!-- Orders Dialog -->
            <Dialog v-model:open="showOrdersModal">
            <DialogContent class="max-h-[90vh] max-w-6xl overflow-hidden p-0">
                <DialogHeader class="border-b px-6 py-4">
                    <DialogTitle>
                        {{ selectedTable?.name || 'Table' }} — Table Orders
                    </DialogTitle>
                    <DialogDescription>
                        Source: <span class="font-semibold">krypton_woosoo</span> • terminal {{ selectedTerminalId || '—' }} • table {{ selectedTable?.id || '—' }}
                    </DialogDescription>
                </DialogHeader>

                <div class="max-h-[62vh] overflow-auto px-6 py-4">
                    <div class="mb-4 rounded-xl border border-black/8 bg-muted/30 p-4 dark:border-white/10">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Add Order by Table</p>
                        <div v-if="props.currentSession?.date_time_closed" class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400">
                            Session closed — orders cannot be added.
                        </div>
                        <template v-else>
                            <div class="flex flex-wrap items-end gap-3">
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Guest Count</label>
                                    <input
                                        v-model.number="form.guest_count"
                                        type="number"
                                        min="1"
                                        class="w-28 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    >
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs text-muted-foreground">Reference</label>
                                    <input
                                        v-model="form.reference"
                                        type="text"
                                        class="w-56 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        placeholder="Optional"
                                    >
                                </div>
                                <Button :disabled="form.processing" @click="addOrderForTable">
                                    Add Order
                                </Button>
                            </div>
                            <div v-if="addOrderError" class="mt-2 rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
                                {{ addOrderError }}
                            </div>
                        </template>
                    </div>

                    <div v-if="ordersLoading" class="space-y-3 py-4">
                        <div v-for="n in 3" :key="n" class="grid grid-cols-7 gap-4 px-2">
                            <Skeleton class="h-6 w-16 rounded" />
                            <Skeleton class="h-6 w-24 rounded" />
                            <Skeleton class="h-6 w-20 rounded" />
                            <Skeleton class="h-6 w-12 rounded" />
                            <Skeleton class="h-6 w-20 rounded" />
                            <Skeleton class="h-6 w-20 rounded" />
                            <div class="flex gap-2 justify-end">
                                <Skeleton class="h-8 w-12 rounded-md" />
                                <Skeleton class="h-8 w-12 rounded-md" />
                                <Skeleton class="h-8 w-12 rounded-md" />
                            </div>
                        </div>
                    </div>

                    <div v-else-if="ordersError" class="rounded-xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                        {{ ordersError }}
                    </div>

                    <div v-else-if="selectedOrders.length === 0" class="rounded-xl border border-black/8 bg-muted/30 px-4 py-8 text-center text-sm text-muted-foreground dark:border-white/10">
                        No open orders found for this device in Krypton.
                    </div>

                    <table v-else class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-2 py-3">Order</th>
                                <th class="px-2 py-3">Opened</th>
                                <th class="px-2 py-3">Resto Table(s)</th>
                                <th class="px-2 py-3 text-right">Guests</th>
                                <th class="px-2 py-3 text-right">Total</th>
                                <th class="px-2 py-3 text-right">Paid</th>
                                <th class="px-2 py-3 text-right">Resettable Txn#</th>
                                <th class="px-2 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="order in selectedOrders" :key="order.id" class="border-b border-black/6 align-top transition-colors hover:bg-black/[0.025] dark:border-white/8 dark:hover:bg-white/[0.03]">
                                <td class="px-2 py-3 font-medium">#{{ order.id }}<br><span class="text-xs text-muted-foreground">{{ order.reference || '—' }}</span></td>
                                <td class="px-2 py-3">{{ order.date_time_opened || '—' }}</td>
                                <td class="px-2 py-3">{{ order.table_names || 'Unassigned' }}</td>
                                <td class="px-2 py-3 text-right">{{ Number(order.guest_count || 0) }}</td>
                                <td class="px-2 py-3 text-right">{{ formatMoney(order.total_amount) }}</td>
                                <td class="px-2 py-3 text-right">{{ formatMoney(order.paid_amount) }}</td>
                                <td class="px-2 py-3 text-right">{{ order.resetable_transaction_number || '—' }}</td>
                                <td class="px-2 py-3">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" :disabled="actionLoading" @click="editOrder(order)">Edit</Button>
                                        <Button size="sm" variant="secondary" :disabled="actionLoading" @click="payOrder(order)">
                                            <ReceiptText class="mr-1 h-3.5 w-3.5" /> Pay
                                        </Button>
                                        <Button size="sm" variant="destructive" :disabled="actionLoading" @click="voidOrder(order)">Void</Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DialogContent>
        </Dialog>
        </div>

        <EditOrderDialog
            :open="editDialogOpen"
            :order="selectedOrderForEdit"
            @update:open="editDialogOpen = $event"
            @save="handleEditSave"
        />

        <PaymentDialog
            :open="paymentDialogOpen"
            :order="selectedOrderForPay"
            @update:open="paymentDialogOpen = $event"
            @pay="handlePay"
        />

        <AlertDialog :open="voidDialogOpen" @update:open="voidDialogOpen = $event">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Void Order #{{ selectedOrderForVoid?.id }}</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to void this order? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="voidDialogOpen = false">Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="handleVoidConfirm">Void Order</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
