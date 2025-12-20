<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center gap-2 flex-wrap">
      <label class="font-medium">Statuses:</label>
      <div v-for="s in ORDER_STATUS_VALUES" :key="s" class="flex items-center gap-1">
        <input type="checkbox" :value="s" v-model="localStatus" @change="apply" />
        <span class="text-sm capitalize">{{ s.replace('_',' ') }}</span>
        <span v-if="counts && counts[s] !== undefined" class="text-xs text-muted-foreground">({{ counts[s] }})</span>
      </div>
      <button class="ml-2 text-xs px-2 py-1 border rounded" @click="clear">Clear</button>
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <label class="font-medium">Search:</label>
      <input class="px-2 py-1 border rounded w-56" type="text" v-model="search" placeholder="Order #, device, table" />
    </div>

    <div class="flex items-center gap-2 flex-wrap">
      <label class="font-medium">Date Range:</label>
      <input class="px-2 py-1 border rounded" type="date" v-model="date_from" />
      <span>to</span>
      <input class="px-2 py-1 border rounded" type="date" v-model="date_to" />
      <button class="text-xs px-2 py-1 border rounded" @click="apply">Apply</button>
    </div>

    <div class="flex items-center gap-2 flex-wrap" v-if="chips.length">
      <label class="font-medium">Active:</label>
      <span v-for="c in chips" :key="c" class="text-xs px-2 py-1 bg-muted rounded">{{ c }}</span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useFilters } from '@/composables/useFilters'
import { ORDER_STATUS_VALUES } from '@/constants/statuses'

const props = defineProps<{
  initial?: { status?: string[] | string; search?: string; date_from?: string; date_to?: string }
  counts?: Record<string, number>
}>()

const { status, search, date_from, date_to, applyFilters, clearFilters } = useFilters(props.initial ?? {})

const localStatus = ref<string[]>(status.value)

const chips = computed(() => {
  const arr: string[] = []
  if (localStatus.value.length) arr.push(`status: ${localStatus.value.join(',')}`)
  if (search.value) arr.push(`search: ${search.value}`)
  if (date_from.value || date_to.value) arr.push(`date: ${date_from.value ?? ''} .. ${date_to.value ?? ''}`)
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

watch([date_from, date_to], () => { /* no-op; user clicks Apply */ })

</script>

<style scoped>
.bg-muted { background-color: rgba(0,0,0,0.06); }
.text-muted-foreground { color: rgba(0,0,0,0.55); }
</style>
