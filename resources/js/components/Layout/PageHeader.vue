<template>
  <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0 flex-1">
      <slot />
    </div>
    <div class="shrink-0">
      <slot name="right" />
    </div>
  </div>
</template>

<script setup lang="ts">
// Reusable page header: left = main content, right = controls
</script>

<style scoped>
/* no scoped styles — layout via Tailwind */
</style>
