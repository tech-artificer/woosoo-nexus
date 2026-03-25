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
  if (level.includes('ERROR')) return 'bg-red-500';
  if (level.includes('WARNING')) return 'bg-yellow-500';
  if (level.includes('INFO')) return 'bg-blue-500';
  return 'bg-gray-500';
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
    
    <div class="space-y-6">
      <div>
        <h1 class="text-2xl font-bold tracking-tight">Event Logs</h1>
        <p class="text-muted-foreground">{{ description }}</p>
      </div>

      <div class="flex flex-col sm:flex-row gap-3">
        <Input 
          v-model="searchQuery" 
          placeholder="Search logs..." 
          class="sm:max-w-xs"
        />
        <Select v-model="levelFilter">
          <SelectTrigger class="sm:w-[180px]">
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

      <div v-if="isSuperAdmin" class="flex items-center gap-2 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded">
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

      <div class="rounded-lg border bg-card">
        <div class="bg-muted/50 text-card-foreground p-4 rounded-t-lg border-b">
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
                  <div class="text-sm mt-1 break-words">
                    {{ entry.message }}
                  </div>
                  <details v-if="showRaw && entry.raw" class="mt-2">
                    <summary class="text-xs text-blue-600 cursor-pointer hover:underline">
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
