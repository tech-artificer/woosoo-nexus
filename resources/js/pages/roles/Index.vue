<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
// import {
//   Tabs,
//   TabsContent,
//   TabsList,
//   TabsTrigger,
// } from '@/components/ui/tabs'
// import { KeyRound } from 'lucide-vue-next'
import { usePage } from '@inertiajs/vue3';
import Permissions from '@/pages/roles/Permissions.vue'
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Roles & Permissions',
    href: '/roles/permissions',
  },
];

defineProps<{
  title?: string;
  description?: string;
}>()

</script>

<template>

  <Head :title="title" :description="description" />

  <AppLayout :breadcrumbs="breadcrumbs">
 
      <Permissions :roles="page.props.roles" :permissions="page.props.permissions"
        :groupedPermissions="page.props.groupedPermissions"
        :assignedPermissions="page.props.assignedPermissions" />
          
  </AppLayout>
</template>