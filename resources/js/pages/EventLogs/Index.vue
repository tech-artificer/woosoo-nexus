<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import AppLayout from '@/layouts/AppLayout.vue';
import { ref } from 'vue';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
  title: string;
  description: string;
  logs: string[];
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Event Logs', href: route('event-logs.index') }
]
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-6 bg-white rounded">
      <h2 class="text-xl font-semibold">Event Logs</h2>
      <p class="text-sm text-muted-foreground">{{ description }}</p>

      <div class="mt-4">
        <div class="bg-gray-900 text-white p-4 rounded max-h-[60vh] overflow-auto font-mono text-xs">
          <template v-if="logs && logs.length">
            <div v-for="(line, idx) in logs" :key="idx" class="whitespace-pre-wrap">{{ line }}</div>
          </template>
          <template v-else>
            <div>No logs found.</div>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
