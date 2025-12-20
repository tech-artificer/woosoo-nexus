<template>
  <div class="mb-4 flex items-center justify-between">
    <div class="flex-1">
      <slot />
    </div>
    <div class="ml-4">
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
