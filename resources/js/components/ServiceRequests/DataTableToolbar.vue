<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'

const props = defineProps({
  search: { type: String, default: '' },
  status: { type: String, default: '' },
  priority: { type: String, default: '' },
  fromDate: { type: String, default: '' },
  toDate: { type: String, default: '' },
  showAll: { type: Boolean, default: false },
})

const emit = defineEmits([
  'update:search',
  'update:status',
  'update:priority',
  'update:fromDate',
  'update:toDate',
  'update:showAll',
  'apply',
  'reset',
])

function apply() {
  emit('apply')
}

function reset() {
  emit('update:search', '')
  emit('update:status', '')
  emit('update:priority', '')
  emit('update:fromDate', '')
  emit('update:toDate', '')
  emit('update:showAll', false)
  emit('reset')
}
</script>

<template>
  <div class="flex flex-col gap-3 px-1 py-1 lg:flex-row lg:items-center lg:justify-between">
    <div class="w-full lg:max-w-sm">
      <Input
        :model-value="search"
        @input="(e: any) => emit('update:search', e.target?.value)"
        @keyup.enter="apply"
        placeholder="Search requests..."
        class="h-8"
      />
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <select :value="status" @change="(e: any) => emit('update:status', e.target?.value)" class="h-8 rounded-md border border-input bg-background px-2 text-sm">
        <option value="">All status</option>
        <option value="pending">Pending</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="voided">Voided</option>
      </select>
      <select :value="priority" @change="(e: any) => emit('update:priority', e.target?.value)" class="h-8 rounded-md border border-input bg-background px-2 text-sm">
        <option value="">All priorities</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
      </select>
      <input type="date" :value="fromDate" @change="(e: any) => emit('update:fromDate', e.target?.value)" class="h-8 rounded-md border border-input bg-background px-2 text-sm" />
      <input type="date" :value="toDate" @change="(e: any) => emit('update:toDate', e.target?.value)" class="h-8 rounded-md border border-input bg-background px-2 text-sm" />
      <label class="flex h-8 items-center gap-2 px-2 text-sm">
        <input type="checkbox" :checked="showAll" @change="(e: any) => emit('update:showAll', e.target?.checked)" />
        <span class="text-sm">Show All</span>
      </label>
      <Button size="sm" class="h-8" @click.prevent="apply">Apply</Button>
      <Button size="sm" variant="outline" class="h-8" @click.prevent="reset">Reset</Button>
    </div>
  </div>
</template>
