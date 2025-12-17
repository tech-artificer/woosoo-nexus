<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
  title?: string
  description?: string
}>()
</script>

<template>
  <Head title="Forbidden" description="You do not have permission to access this page" />
  <AppLayout :breadcrumbs="[{ title: 'Forbidden', href: '#' }]">
    <div class="flex h-full items-center justify-center p-8">
      <div class="max-w-lg text-center">
        <h1 class="text-4xl font-bold">403 — Access denied</h1>
        <p class="mt-4 text-muted-foreground">
          You don't have permission to view this page. If you believe this is an error, contact your system administrator.
        </p>
        <div class="mt-6">
          <a href="/" class="btn btn-primary">Return home</a>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
