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
        <div class="flex h-full flex-1 flex-col gap-6">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-2xl font-semibold text-gray-900">Device Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage registered devices and activation codes</p>
            </div>

            <!-- Tabs Section -->
            <div class="bg-white rounded-lg shadow-sm">
                <Tabs default-value="devices" class="w-full">
                    <div class="border-b border-gray-200 px-6 pt-4">
                        <TabsList class="grid w-full max-w-md grid-cols-2 bg-gray-100">
                            <TabsTrigger value="devices" class="data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                Devices
                            </TabsTrigger>
                            <TabsTrigger value="codes" class="data-[state=active]:bg-white data-[state=active]:shadow-sm">
                                Codes
                            </TabsTrigger>
                        </TabsList>
                    </div>

                    <TabsContent value="devices" class="p-6 pt-4 space-y-4">
                        <!-- Download Buttons -->
                        <div class="flex flex-wrap justify-end gap-3">
                            <Button as-child size="sm">
                                <a :href="downloadReleaseUrl" download>Download Printer APK (Release)</a>
                            </Button>
                            <Button variant="secondary" as-child size="sm">
                                <a :href="downloadDebugUrl" download>Download Printer APK (Debug)</a>
                            </Button>
                            <Button variant="outline" as-child size="sm">
                                <a :href="downloadCertificateUrl" download>Download CA Certificate</a>
                            </Button>
                        </div>

                        <!-- SSL Setup Notice -->
                        <div class="text-sm p-4 bg-blue-50 border border-blue-200 rounded-lg">
                            <strong class="text-blue-900">SSL Certificate Setup:</strong>
                            <span class="text-blue-700"> Download <code class="bg-white px-2 py-1 rounded border border-blue-200">woosoo-ca.pem</code> and install on your device to enable secure WebSocket connections. On Android: Settings → Security → Install from SD card.</span>
                        </div>

                        <!-- Stats Cards -->
                        <StatsCards :cards="(stats ?? [
                            { title: 'Total Devices', value: (devices || []).length, subtitle: 'Registered devices', variant: 'primary' },
                            { title: 'Registration Codes', value: registrationCodes?.length ?? 0, subtitle: 'Available codes', variant: 'accent' },
                        ])" />

                        <!-- Devices Table -->
                        <DataTable :data="devices" :columns="columns" />
                    </TabsContent>

                    <TabsContent value="codes" class="p-6 pt-4">
                        <Card class="shadow-sm">
                            <CardHeader class="flex items-center justify-between">
                                <div>
                                    <CardTitle>Registration Codes</CardTitle>
                                    <CardDescription>
                                        Generate device codes for device activation
                                    </CardDescription>
                                </div>
                                <div class="ml-4">
                                    <Button v-if="!hasGeneratedCodes" @click.prevent="generateCodes">Generate 15 Codes</Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableCaption>Device registration codes and usage status</TableCaption>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Code</TableHead>
                                            <TableHead>Device ID</TableHead>
                                            <TableHead>Registered At</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="code in registrationCodes" :key="code.id">
                                            <TableCell class="font-mono">{{ code.code }}</TableCell>
                                            <TableCell>{{ code.used_by_device_id ?? '-' }}</TableCell>
                                            <TableCell>{{ code.used_at ?? 'Not used' }}</TableCell>
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
        </div>
    </AppLayout>
</template>
 