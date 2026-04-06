<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center gap-3 flex-wrap">
      <span class="text-sm font-medium">Statuses:</span>
      <div v-for="s in ORDER_STATUS_VALUES" :key="s" class="flex items-center gap-2">
        <Checkbox :id="`status-${s}`" :value="s" :checked="localStatus.includes(s)" @update:checked="toggleStatus(s)" />
        <Label :for="`status-${s}`" class="text-sm capitalize cursor-pointer">
          {{ s.replace('_', ' ') }}
          <span v-if="counts && counts[s] !== undefined" class="text-muted-foreground">({{ counts[s] }})</span>
        </Label>
      </div>
      <Button variant="ghost" size="sm" @click="clear">Clear</Button>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-sm font-medium">Search:</span>
      <Input
        class="w-56"
        type="text"
        v-model="search"
        placeholder="Order #, device, table"
        aria-label="Search orders"
      />
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-sm font-medium">Date Range:</span>
      <Input class="w-36" type="date" v-model="date_from" aria-label="From date" />
      <span class="text-sm text-muted-foreground">to</span>
      <Input class="w-36" type="date" v-model="date_to" aria-label="To date" />
      <Button variant="outline" size="sm" @click="apply">Apply</Button>
    </div>

    <div v-if="chips.length" class="flex items-center gap-2 flex-wrap">
      <span class="text-sm font-medium">Active:</span>
      <Badge v-for="c in chips" :key="c" variant="secondary">{{ c }}</Badge>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useFilters } from '@/composables/useFilters'
import { ORDER_STATUS_VALUES } from '@/constants/statuses'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'

const props = defineProps<{
  initial?: { status?: string[] | string; search?: string; date_from?: string; date_to?: string }
  counts?: Record<string, number>
}>()

const { status, search, date_from, date_to, applyFilters, clearFilters } = useFilters(props.initial ?? {})

const localStatus = ref<string[]>(Array.isArray(status.value) ? [...status.value] : [])

const toggleStatus = (s: string) => {
  const idx = localStatus.value.indexOf(s)
  if (idx === -1) {
    localStatus.value = [...localStatus.value, s]
  } else {
    localStatus.value = localStatus.value.filter(v => v !== s)
  }
  apply()
}

const chips = computed(() => {
  const arr: string[] = []
  if (localStatus.value.length) arr.push(`status: ${localStatus.value.join(', ')}`)
  if (search.value) arr.push(`search: ${search.value}`)
  if (date_from.value || date_to.value) arr.push(`date: ${date_from.value ?? ''} – ${date_to.value ?? ''}`)
  return arr
})

const apply = () => {
  status.value = [...localStatus.value]
  applyFilters()
}

const clear = () => {
  localStatus.value = []
  clearFilters()
}
</script>
