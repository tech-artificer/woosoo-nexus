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
    <div class="flex h-full flex-1 flex-col bg-white gap-4 rounded p-6">
      <h2 class="text-lg font-semibold">Edit User</h2>
      <UserForm :user="user" form-type="edit" />
    </div>
  </AppLayout>
</template>
