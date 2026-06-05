<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';
import UserForm from '@/components/Users/UserForm.vue';
const breadcrumbs: BreadcrumbItem[] = [
  { title: 'User Management', href: route('users.index') }
];

defineProps<{
  title: string;
  description: string;
  user: any;
  roles: any[];
  branches: any[];
}>()
</script>

<template>
  <Head :title="title" :description="description" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-5">
      <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
        <div class="relative space-y-3">
          <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
            User management
          </span>
          <div>
            <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Edit User</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">{{ description }}</p>
          </div>
        </div>
      </div>

      <div class="rounded-[26px] border border-black/8 bg-card/92 p-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 sm:p-6">
        <UserForm :user="user" form-type="edit" />
      </div>
    </div>
  </AppLayout>
</template>
