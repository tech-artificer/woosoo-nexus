<script setup lang="ts">
import { computed } from 'vue'
import type { Device } from '@/types/models'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Separator } from '@/components/ui/separator'
import { Clock3, Landmark, Monitor, Wifi } from 'lucide-vue-next'

interface DeviceDetailSheetProps {
  open: boolean
  device: Device | null
}

const props = defineProps<DeviceDetailSheetProps>()
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

const openState = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
})

const rawDevice = computed(() => (props.device ?? null) as any)

const safeText = (value: any, fallback = '-') => (value ?? value === 0 ? String(value) : fallback)

const statusVariant = computed(() => {
  if (!props.device) return 'secondary'
  if (props.device.deleted_at) return 'destructive'
  if (props.device.is_active) return 'success'
  return 'secondary'
})
</script>

<template>
  <Sheet v-model:open="openState">
    <SheetContent side="right" class="w-full sm:max-w-3xl">
      <SheetHeader>
        <SheetTitle class="text-xl">{{ safeText(device?.name, 'Device') }}</SheetTitle>
        <SheetDescription>
          Device details and assignment overview
        </SheetDescription>
      </SheetHeader>

      <div class="mt-6 grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle class="text-sm text-muted-foreground">Device Status</CardTitle>
            <CardDescription class="flex items-center gap-2">
              <Badge :variant="statusVariant">{{ device?.deleted_at ? 'deactivated' : (device?.is_active ? 'active' : 'inactive') }}</Badge>
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Name</span>
              <span class="font-medium">{{ safeText(device?.name) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Serial</span>
              <span class="font-medium">{{ safeText(rawDevice?.serial_no) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Type</span>
              <span class="font-medium">{{ safeText(rawDevice?.type) }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm text-muted-foreground">Network</CardTitle>
            <CardDescription>Current and recent connectivity</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 text-muted-foreground"><Wifi class="size-4" /> IP</span>
              <span class="font-medium">{{ safeText(device?.ip_address) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 text-muted-foreground"><Monitor class="size-4" /> Port</span>
              <span class="font-medium">{{ safeText(device?.port) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Last IP</span>
              <span class="font-medium">{{ safeText(rawDevice?.last_ip_address) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 text-muted-foreground"><Clock3 class="size-4" /> Last Seen</span>
              <span class="font-medium">{{ safeText(rawDevice?.last_seen_at) }}</span>
            </div>
          </CardContent>
        </Card>

        <Card class="md:col-span-2">
          <CardHeader>
            <CardTitle class="text-sm text-muted-foreground">Assignment</CardTitle>
            <CardDescription>Branch and table mapping</CardDescription>
          </CardHeader>
          <CardContent class="grid gap-3 text-sm md:grid-cols-2">
            <div class="rounded-md border p-3">
              <div class="mb-2 flex items-center gap-2 text-muted-foreground"><Landmark class="size-4" /> Branch</div>
              <div class="font-medium">{{ safeText(device?.branch?.name) }}</div>
            </div>
            <div class="rounded-md border p-3">
              <div class="mb-2 text-muted-foreground">Table</div>
              <div class="font-medium">{{ safeText(device?.table?.name) }}</div>
            </div>
            <Separator class="md:col-span-2" />
            <div class="rounded-md border p-3 md:col-span-2">
              <div class="mb-2 text-muted-foreground">Registration Code</div>
              <div class="font-semibold">{{ safeText(device?.registration_code?.code) }}</div>
            </div>
          </CardContent>
        </Card>
      </div>
    </SheetContent>
  </Sheet>
</template>
