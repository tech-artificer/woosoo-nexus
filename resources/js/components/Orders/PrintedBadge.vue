<script setup lang="ts">
import { computed } from 'vue'
import { CheckCircle, Clock } from 'lucide-vue-next'
import { formatDistanceToNow, parseISO } from 'date-fns'

interface Props {
  printedAt?: string | null
  isPrinted?: boolean
  printerId?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  printedAt: null,
  isPrinted: false,
  printerId: null,
})

const relativeTime = computed(() => {
  if (!props.printedAt) return null
  try {
    return formatDistanceToNow(parseISO(props.printedAt), { addSuffix: true })
  } catch {
    return null
  }
})

const printerLabel = computed(() => {
  if (!props.printerId) return null
  return `Printed by: ${props.printerId}`
})
</script>

<template>
  <div v-if="isPrinted" class="flex items-center gap-2">
    <CheckCircle class="h-4 w-4 text-green-600" :title="printerLabel || 'Printed'" />
    <span v-if="relativeTime" class="text-xs text-gray-600">{{ relativeTime }}</span>
  </div>
  <div v-else class="flex items-center gap-2 text-gray-400">
    <Clock class="h-4 w-4" title="Pending print" />
    <span class="text-xs">Pending</span>
  </div>
</template>
