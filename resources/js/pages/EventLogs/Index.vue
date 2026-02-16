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
    <div class="flex h-full flex-1 flex-col gap-6">
      <!-- Header Section -->
      <div class="bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-2xl font-semibold text-gray-900">Event Logs</h1>
        <p class="text-sm text-gray-500 mt-1">{{ description }}</p>
      </div>

      <!-- Logs Section -->
      <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="bg-gray-50 text-gray-700 px-6 py-4 border-b border-gray-200">
          <span class="text-sm font-medium">Application Logs</span>
        </div>
        <div class="bg-slate-900 text-slate-100 p-6 max-h-[65vh] overflow-auto font-mono text-xs">
          <template v-if="logs && logs.length">
            <div v-for="(line, idx) in logs" :key="idx" class="whitespace-pre-wrap py-0.5 hover:bg-slate-800/50">{{ line }}</div>
          </template>
          <template v-else>
            <div class="text-slate-400">No logs found.</div>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
