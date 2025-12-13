<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { defineEmits, defineProps } from 'vue'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { computed } from 'vue'

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
  <div class="flex flex-col md:flex-row items-start md:items-center gap-3 mb-3">
    <div class="flex-1">
      <input :value="search" @input="(e) => emit('update:search', e.target.value)" @keyup.enter="apply" placeholder="Search requests..." class="border rounded px-3 py-2 w-full" />
    </div>
    <div class="flex items-center gap-2">
      <select :value="status" @change="(e) => emit('update:status', e.target.value)" class="border rounded px-3 py-2">
        <option value="">All status</option>
        <option value="pending">Pending</option>
        <option value="in_progress">In Progress</option>
        <option value="completed">Completed</option>
        <option value="voided">Voided</option>
      </select>
      <select :value="priority" @change="(e) => emit('update:priority', e.target.value)" class="border rounded px-3 py-2">
        <option value="">All priorities</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
        <option value="urgent">Urgent</option>
      </select>
      <input type="date" :value="fromDate" @change="(e) => emit('update:fromDate', e.target.value)" class="border rounded px-3 py-2" />
      <input type="date" :value="toDate" @change="(e) => emit('update:toDate', e.target.value)" class="border rounded px-3 py-2" />
      <label class="flex items-center gap-2">
        <input type="checkbox" :checked="showAll" @change="(e) => emit('update:showAll', e.target.checked)" />
        <span class="text-sm">Show All</span>
      </label>
      <button @click.prevent="apply" class="px-3 py-2 rounded bg-primary text-white">Apply</button>
      <button @click.prevent="reset" class="px-3 py-2 rounded border">Reset</button>
    </div>
  </div>
</template>
