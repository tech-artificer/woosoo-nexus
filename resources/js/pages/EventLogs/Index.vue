<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';

interface LogEntry {
  timestamp: string | null;
  level: string;
  message: string;
  raw?: string | null;
}

const props = defineProps<{
  title: string;
  description: string;
  logs: LogEntry[];
  isSuperAdmin: boolean;
}>()

const showRaw = ref(false);
const searchQuery = ref('');
const levelFilter = ref('all');

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Event Logs', href: route('event-logs.index') }
]

const getLevelColor = (level: string) => {
  if (level.includes('ERROR')) return 'bg-destructive';
  if (level.includes('WARNING')) return 'bg-woosoo-accent text-woosoo-dark-gray';
  if (level.includes('INFO')) return 'bg-woosoo-blue';
  return 'bg-muted-foreground';
}

const filteredLogs = computed(() => {
  let filtered = props.logs;

  // Filter by level
  if (levelFilter.value !== 'all') {
    filtered = filtered.filter(log => 
      log.level.toUpperCase().includes(levelFilter.value.toUpperCase())
    );
  }

  // Filter by search query
  if (searchQuery.value.trim()) {
    const query = searchQuery.value.toLowerCase();
    filtered = filtered.filter(log =>
      log.message.toLowerCase().includes(query) ||
      (log.timestamp && log.timestamp.toLowerCase().includes(query))
    );
  }

  return filtered;
});
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="title" />
    
    <div class="space-y-5">
      <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
        <div class="relative space-y-3">
          <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
            System
          </span>
          <div>
            <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">{{ title }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">{{ description }}</p>
          </div>
        </div>
      </div>

      <div class="flex flex-col sm:flex-row gap-3">
        <Input 
          v-model="searchQuery" 
          placeholder="Search logs..." 
          class="sm:max-w-xs"
        />
        <Select v-model="levelFilter">
          <SelectTrigger class="sm:w-45">
            <SelectValue placeholder="Filter by level" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Levels</SelectItem>
            <SelectItem value="error">Errors</SelectItem>
            <SelectItem value="warning">Warnings</SelectItem>
            <SelectItem value="info">Info</SelectItem>
          </SelectContent>
        </Select>
      </div>

      <div v-if="isSuperAdmin" class="flex items-center gap-2 rounded-xl border border-woosoo-accent/25 bg-woosoo-accent/10 p-3">
        <input 
          type="checkbox" 
          id="show-raw" 
          v-model="showRaw" 
          class="rounded"
        />
        <label for="show-raw" class="text-sm font-medium">
          Show raw stack traces (super-admin only)
        </label>
      </div>

      <div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
        <div class="border-b border-black/8 bg-muted/35 p-4 text-card-foreground dark:border-white/10">
          <span class="text-sm font-medium">Application Logs (Sanitized)</span>
        </div>
        <div class="p-4 max-h-[60vh] overflow-auto space-y-2">
          <template v-if="filteredLogs.length">
            <div 
              v-for="(entry, idx) in filteredLogs" 
              :key="idx" 
              class="border-b pb-2 last:border-0"
            >
              <div class="flex items-start gap-2">
                <Badge :class="getLevelColor(entry.level)" class="text-white shrink-0">
                  {{ entry.level }}
                </Badge>
                <div class="flex-1 min-w-0">
                  <div v-if="entry.timestamp" class="text-xs text-muted-foreground">
                    {{ entry.timestamp }}
                  </div>
                  <div class="text-sm mt-1 wrap-break-word">
                    {{ entry.message }}
                  </div>
                  <details v-if="showRaw && entry.raw" class="mt-2">
                    <summary class="cursor-pointer text-xs text-woosoo-blue hover:underline">
                      Show full stack trace
                    </summary>
                    <pre class="mt-2 text-xs bg-slate-900 text-slate-100 p-2 rounded overflow-x-auto">{{ entry.raw }}</pre>
                  </details>
                </div>
              </div>
            </div>
          </template>
          <template v-else>
            <div class="text-slate-400 text-center py-8">No log entries match the current filters.</div>
          </template>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
