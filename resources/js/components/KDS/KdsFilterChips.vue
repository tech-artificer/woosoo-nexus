<script setup lang="ts">
import type { KdsFilter } from './kdsTypes'

defineProps<{
  modelValue: KdsFilter
  counts: Record<KdsFilter, number>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: KdsFilter]
}>()

const groups: Array<Array<{ value: KdsFilter; label: string }>> = [
  [
    { value: 'active', label: 'All Active' },
    { value: 'overdue', label: 'Overdue' },
  ],
  [
    { value: 'new', label: 'New' },
    { value: 'preparing', label: 'Preparing' },
    { value: 'ready', label: 'Ready' },
  ],
  [
    { value: 'served', label: 'Served' },
    { value: 'voided', label: 'Voided' },
  ],
]
</script>

<template>
  <nav class="kds-filters" aria-label="Kitchen display filters">
    <template v-for="(group, index) in groups" :key="index">
      <div v-if="index > 0" class="kds-filter-divider" aria-hidden="true" />
      <button
        v-for="item in group"
        :key="item.value"
        type="button"
        class="kds-filter-chip"
        :class="{ 'is-active': modelValue === item.value }"
        :aria-pressed="modelValue === item.value"
        @click="emit('update:modelValue', item.value)"
      >
        <span>{{ item.label }}</span>
        <strong>{{ counts[item.value] }}</strong>
      </button>
    </template>
  </nav>
</template>
