<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'

interface Props {
  status: string | undefined
}

const props = defineProps<Props>()

const statusConfig = computed(() => {
  const configs: Record<string, { variant: string; label: string; class?: string }> = {
    pending: { variant: 'outline', label: 'Pending' },
    confirmed: { variant: 'accent', label: 'Confirmed' },
    in_progress: { variant: 'default', label: 'In Progress' },
    ready: { variant: 'success', label: 'Ready' },
    served: { variant: 'success', label: 'Served' },
    completed: { variant: 'success', label: 'Completed' },
    voided: { variant: 'destructive', label: 'Voided' },
    cancelled: { variant: 'destructive', label: 'Cancelled' },
    archived: { variant: 'secondary', label: 'Archived' },
  }

  const key = String(props.status ?? '').toLowerCase()
  return configs[key] ?? { variant: 'default', label: props.status ?? 'Unknown' }
})
</script>

<template>
  <Badge :variant="statusConfig.variant as any" class="font-medium">
    {{ statusConfig.label }}
  </Badge>
</template>
