<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from "@/components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from "@/components/ui/tabs"
import { type BreadcrumbItem } from '@/types';

import { columns } from '@/components/Devices/columns';
import DataTable from '@/components/Devices/DataTable.vue'
import StatsCards from '@/components/Stats/StatsCards.vue'
import { router } from '@inertiajs/core'
import axios from 'axios'
import { ref, computed, toRefs } from 'vue'
import type { Device } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Devices',
        href: route('devices.index'),
    },
];

const props = defineProps<{
    title: string;
    description: string;
    devices: Device[];
    registrationCodes: any[];
    stats?: any;
}>()

const { devices, registrationCodes, stats } = toRefs(props)

const newCodes = ref<Array<{id:number, code:string}>>([])
const search = ref('')

const statusFilter = ref('')

const originalDevices = computed(() => devices.value ?? [])

const downloadReleaseUrl = computed(() => route('devices.download-apk', { channel: 'release' }))
const downloadDebugUrl = computed(() => route('devices.download-apk', { channel: 'debug' }))
const downloadCertificateUrl = computed(() => route('devices.download-certificate'))

const hasGeneratedCodes = computed(() => {
    // Check if codes exist (either newly generated or from server)
    return newCodes.value.length > 0 || (registrationCodes.value?.length ?? 0) > 0
})

const filteredDevices = computed(() => {
    return originalDevices.value.filter((d: any) => {
        if (search.value) {
            const q = search.value.toLowerCase()
            if (!((d.name || '').toLowerCase().includes(q) || (d.serial_no || '').toLowerCase().includes(q))) return false
        }
        if (statusFilter.value) {
            if (statusFilter.value === 'registered' && !d.is_registered) return false
            if (statusFilter.value === 'assigned' && !d.assigned_to) return false
            if (statusFilter.value === 'inactive' && d.is_active) return false
        }
        return true
    })
})

function submitSearch() { /* client-side — no-op */ }
function submitFilters() { /* client-side — reactive */ }
function resetFilters() { search.value = ''; statusFilter.value = '' }

async function generateCodes() {
    const count = 15
    try {
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        const res = await axios.post(
            route('devices.generate.codes'),
            { count },
            {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            }
        )
        if (res?.data?.success) {
            newCodes.value = (res.data.created || []).map((c: any) => ({ id: c.id, code: c.code }))
            // reload page so server data stays in sync
            try {
                window.location.href = route('devices.index')
            } catch (e) {
                // fallback to root
                window.location.href = '/devices'
            }
        }
    } catch (err: any) {
        console.error('Failed to generate codes', err)
        // fallback: prefer Inertia visit; if that fails, do a full-page redirect
        try {
            // best-effort full-page POST fallback
            const form = document.createElement('form')
            form.method = 'POST'
            form.action = route('devices.generate.codes')
            const input = document.createElement('input')
            input.type = 'hidden'
            input.name = 'count'
            input.value = String(count)
            form.appendChild(input)
            document.body.appendChild(form)
            form.submit()
        } catch (e) {
            try { window.location.href = route('devices.generate.codes') } catch { window.location.href = '/devices' }
        }
    }
}

function exportCSV() {
    const rows = newCodes.value.length
        ? newCodes.value
        : (registrationCodes.value || []).map((c: any) => ({ id: c.id, code: c.code }))
    if (!rows || !rows.length) return
    const csv = ['id,code', ...rows.map(r => `${r.id},${r.code}`)].join('\n')
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `device_codes_${new Date().toISOString().slice(0,10)}.csv`
    document.body.appendChild(a)
    a.click()
    a.remove()
    URL.revokeObjectURL(url)
}

</script>

<template>


    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Device Management</h1>
                <p class="text-muted-foreground">Manage registered devices and activation codes</p>
            </div>
            <Tabs default-value="devices" class="">
                <TabsList class="grid w-full grid-cols-2">
                    <TabsTrigger value="devices">
                        Devices
                    </TabsTrigger>
                    <TabsTrigger value="codes">
                        Codes
                    </TabsTrigger>
                </TabsList>
                <TabsContent value="devices" class="p-2">
                                <!-- Filters moved into Devices DataTable toolbar -->
                    <div class="flex flex-wrap justify-end gap-3 mb-3">
                        <Button as-child>
                            <a :href="downloadReleaseUrl" download>Download Printer APK (Release)</a>
                        </Button>
                        <Button variant="secondary" as-child>
                            <a :href="downloadDebugUrl" download>Download Printer APK (Debug)</a>
                        </Button>
                        <Button variant="outline" as-child>
                            <a :href="downloadCertificateUrl" download>Download CA Certificate</a>
                        </Button>
                    </div>
                    <div class="text-sm text-muted-foreground mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                        <strong>SSL Certificate Setup:</strong> Download <code class="bg-white px-2 py-1 rounded">woosoo-ca.pem</code> and install on your device to enable secure WebSocket connections. On Android: Settings → Security → Install from SD card.
                    </div>
                    <StatsCards :cards="(stats ?? [
                        { title: 'Total Devices', value: (devices || []).length, subtitle: 'Registered devices', variant: 'primary' },
                        { title: 'Registration Codes', value: registrationCodes?.length ?? 0, subtitle: 'Available codes', variant: 'accent' },
                    ])" />
                    <DataTable :data="devices" :columns="columns" />
                </TabsContent>
                <TabsContent value="codes">
                    <Card>
                        <CardHeader class="flex items-center justify-between">
                            <div>
                                <CardTitle>Codes</CardTitle>
                                <CardDescription>
                                    Generate device codes for device activation.
                                </CardDescription>
                            </div>
                            <div class="ml-4">
                                <Button v-if="!hasGeneratedCodes" @click.prevent="generateCodes">Generate 15 Codes</Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                           
                               <!-- {{ registrationCodes }} -->
                               <Table>
                                    <TableCaption>A list of your recent invoices.</TableCaption>
                                    <TableHeader>
                                    <TableRow>
                                        <TableHead class="">
                                        Code
                                        </TableHead>
                                       <TableHead class="">
                                        Device ID
                                        </TableHead> 
                                        <TableHead class="">
                                            Registered At
                                        </TableHead> 
                                    </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                    <TableRow v-for="code in registrationCodes" :key="code.id">
                                        <TableCell>
                                        {{ code.code }}
                                        </TableCell>
                                        <TableCell>{{ code.used_by_device_id }}</TableCell>
                                        <TableCell>{{ code.used_at }}</TableCell>
                                    </TableRow>
                                    </TableBody>
                                </Table>
                          
                        </CardContent>
                        <CardFooter class="flex items-center gap-3">
                            <div class="ml-auto flex items-center gap-2">
                                <Button @click.prevent="exportCSV" variant="default">Export CSV</Button>
                            </div>
                        </CardFooter>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </AppLayout>
</template>
 