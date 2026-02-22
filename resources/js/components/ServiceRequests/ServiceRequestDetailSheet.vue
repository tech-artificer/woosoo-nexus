<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Bell, Clock3, Flag, Utensils } from 'lucide-vue-next'

interface ServiceRequestLike {
  id?: number
  table_name?: string
  table_service_name?: string
  description?: string
  status?: string
  priority?: string
  created_at?: string
  updated_at?: string
  is_active?: boolean
}

interface ServiceRequestDetailSheetProps {
  open: boolean
  request: ServiceRequestLike | null
}

const props = defineProps<ServiceRequestDetailSheetProps>()
const emit = defineEmits<{
  (e: 'update:open', value: boolean): void
}>()

const openState = computed({
  get: () => props.open,
  set: (value: boolean) => emit('update:open', value),
})

const safeText = (value: any, fallback = '-') => (value ?? value === 0 ? String(value) : fallback)

const statusVariant = computed(() => {
  const status = String(props.request?.status || '').toLowerCase()
  if (['resolved', 'completed', 'served'].includes(status)) return 'success'
  if (['cancelled', 'rejected'].includes(status)) return 'destructive'
  if (['pending'].includes(status)) return 'outline'
  return 'secondary'
})

const priorityVariant = computed(() => {
  const priority = String(props.request?.priority || '').toLowerCase()
  if (['urgent', 'high'].includes(priority)) return 'destructive'
  if (['medium'].includes(priority)) return 'active'
  return 'secondary'
})
</script>

<template>
  <Sheet v-model:open="openState">
    <SheetContent side="right" class="w-full sm:max-w-3xl">
      <SheetHeader>
        <SheetTitle class="text-xl">Service Request {{ safeText(request?.id) }}</SheetTitle>
        <SheetDescription>
          {{ safeText(request?.table_name, 'Unknown Table') }} • {{ safeText(request?.table_service_name, 'General') }}
        </SheetDescription>
      </SheetHeader>

      <div class="mt-6 grid gap-4 md:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle class="text-sm text-muted-foreground">Request Status</CardTitle>
            <CardDescription class="flex items-center gap-2">
              <Badge :variant="statusVariant">{{ safeText(request?.status, 'pending') }}</Badge>
              <Badge :variant="priorityVariant">{{ safeText(request?.priority, 'normal') }}</Badge>
            </CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Table</span>
              <span class="font-medium">{{ safeText(request?.table_name) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Service</span>
              <span class="font-medium">{{ safeText(request?.table_service_name) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-muted-foreground">Active</span>
              <span class="font-medium">{{ request?.is_active ? 'Yes' : 'No' }}</span>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle class="text-sm text-muted-foreground">Timing</CardTitle>
            <CardDescription>Lifecycle timestamps</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3 text-sm">
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 text-muted-foreground"><Clock3 class="size-4" /> Created</span>
              <span class="font-medium">{{ safeText(request?.created_at) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="flex items-center gap-2 text-muted-foreground"><Bell class="size-4" /> Updated</span>
              <span class="font-medium">{{ safeText(request?.updated_at) }}</span>
            </div>
          </CardContent>
        </Card>

        <Card class="md:col-span-2">
          <CardHeader>
            <CardTitle class="text-sm text-muted-foreground">Request Detail</CardTitle>
            <CardDescription>Operator-facing context</CardDescription>
          </CardHeader>
          <CardContent class="space-y-3">
            <div class="rounded-md border p-3 text-sm">
              <div class="mb-2 flex items-center gap-2 text-muted-foreground"><Utensils class="size-4" /> Description</div>
              <div class="font-medium">{{ safeText(request?.description, 'No additional details provided') }}</div>
            </div>
            <div class="rounded-md border p-3 text-sm">
              <div class="mb-2 flex items-center gap-2 text-muted-foreground"><Flag class="size-4" /> Priority</div>
              <div class="font-semibold">{{ safeText(request?.priority, 'normal') }}</div>
            </div>
          </CardContent>
        </Card>
      </div>
    </SheetContent>
  </Sheet>
</template>
