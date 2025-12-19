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
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">Event Logs</h1>
        <p class="text-muted-foreground">{{ description }}</p>
      </div>

      <div class="rounded-lg border bg-card">
        <div class="bg-muted/50 text-card-foreground p-4 rounded-t-lg border-b">
          <span class="text-sm font-medium">Application Logs</span>
        </div>
        <div class="bg-slate-900 text-slate-100 p-4 rounded-b-lg max-h-[60vh] overflow-auto font-mono text-xs">
          <template v-if="logs && logs.length">
            <div v-for="(line, idx) in logs" :key="idx" class="whitespace-pre-wrap py-0.5">{{ line }}</div>
          </template>
          <template v-else>
            <div class="text-slate-400">No logs found.</div>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
