<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Download } from 'lucide-vue-next'

const props = withDefaults(defineProps<{
  startDate?: string
  endDate?: string
  exportRoute?: string
}>(), {
  startDate: '',
  endDate: '',
})

const localStart = ref(props.startDate || '')
const localEnd = ref(props.endDate || '')

function applyRange() {
  router.get(window.location.pathname, {
    start_date: localStart.value || undefined,
    end_date: localEnd.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
  })
}

function exportCsv() {
  if (!props.exportRoute) return
  const params = new URLSearchParams()
  if (localStart.value) params.set('start_date', localStart.value)
  if (localEnd.value) params.set('end_date', localEnd.value)
  const qs = params.toString()
  window.location.href = qs ? `${props.exportRoute}?${qs}` : props.exportRoute
}
</script>

<template>
  <div class="flex flex-wrap items-end gap-3 rounded-[18px] border border-black/8 bg-card/60 p-4 dark:border-white/10">
    <div class="space-y-1">
      <label class="text-xs font-medium text-muted-foreground">Start date</label>
      <Input v-model="localStart" type="date" class="h-9 w-40" />
    </div>
    <div class="space-y-1">
      <label class="text-xs font-medium text-muted-foreground">End date</label>
      <Input v-model="localEnd" type="date" class="h-9 w-40" />
    </div>
    <Button size="sm" class="h-9" @click="applyRange">Apply</Button>
    <Button
      v-if="exportRoute"
      variant="outline"
      size="sm"
      class="h-9"
      @click="exportCsv"
    >
      <Download class="mr-2 h-4 w-4" />
      Export CSV
    </Button>
  </div>
</template>
