<script setup lang="ts">
 
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

import { columns } from '@/components/Devices/columns';
import DataTable from '@/components/Devices/DataTable.vue'
import DeviceDetailSheet from '@/components/Devices/DeviceDetailSheet.vue'
import { ref, computed, toRefs } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
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
    stats?: any;
}>()

const { devices, stats } = toRefs(props)

const selectedDevice = ref<Device | null>(null)
const isDeviceDetailOpen = ref(false)
const page = usePage()

const revealedSecurityCode = computed(() => String((page.props as any)?.flash?.security_code_reveal || ''))
const showSecurityCodeReveal = ref(Boolean(revealedSecurityCode.value))
const originalDevices = computed(() => devices.value ?? [])
const securityReadyCount = computed(() =>
    originalDevices.value.filter((device: any) => Boolean(device.security_code_generated_at)).length
)

const openDeviceDetail = (device: Device) => {
    selectedDevice.value = device
    isDeviceDetailOpen.value = true
}

</script>

<template>
    <Head :title="title" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                    <div class="flex min-w-0 flex-1 flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div class="max-w-2xl space-y-2">
                            <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                Device management
                            </span>
                            <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                                Devices
                            </h2>
                            <p class="max-w-xl text-sm leading-6 text-muted-foreground sm:text-base">
                                Register a new device, review the devices that are already online, and rotate security codes when a tablet needs to be re-issued.
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Button as-child size="lg" class="w-full sm:w-auto">
                                <Link :href="route('devices.create')">
                                    Create Device
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 lg:w-[420px]">
                        <div class="rounded-[18px] border border-black/8 bg-white/72 px-4 py-4 dark:border-white/10 dark:bg-white/[0.06]">
                            <p class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">Total Devices</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
                                {{ stats?.total_devices ?? (devices || []).length }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">Registered devices</p>
                        </div>
                        <div class="rounded-[18px] border border-black/8 bg-white/72 px-4 py-4 dark:border-white/10 dark:bg-white/[0.06]">
                            <p class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">Security Ready</p>
                            <p class="mt-2 text-2xl font-semibold tracking-tight tabular-nums">
                                {{ stats?.security_ready ?? securityReadyCount }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">Devices with security code</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="p-4 sm:p-6">
                    <DataTable
                        :data="devices"
                        :columns="columns"
                        :empty-action-href="route('devices.create')"
                        empty-action-label="Create Device"
                        @row-click="openDeviceDetail"
                    />
                </div>
            </section>

            <DeviceDetailSheet
                v-model:open="isDeviceDetailOpen"
                :device="selectedDevice"
            />

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
        </div>
    </AppLayout>
</template>
 
