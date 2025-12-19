<script setup lang="ts">
import PageHeader from '@/components/Layout/PageHeader.vue'
</script>

<template>
   <div class="flex h-full flex-1 flex-col min-h-0 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 gap-4 rounded-lg p-4 md:p-6">
      <PageHeader>
         <slot name="header" />
      </PageHeader>
      <slot />
   </div>
</template>