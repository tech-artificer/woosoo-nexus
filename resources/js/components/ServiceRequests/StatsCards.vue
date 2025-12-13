<template>
  <div class="grid grid-cols-4 gap-4">
    <div class="p-3 border rounded" v-for="(value, key) in stats" :key="key">
      <div class="text-sm text-muted">{{ key.replace(/_/g, ' ') }}</div>
      <div class="text-lg font-semibold">{{ value }}</div>
    </div>
  </div>
</template>

<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
const props = defineProps({ stats: { type: Object, default: () => ({}) } })
</script>
