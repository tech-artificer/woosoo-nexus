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
const roleProps = page.props as any

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Permission Registry',
    href: route('roles.index'),
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
 
      <Permissions :roles="roleProps.roles" :permissions="roleProps.permissions"
        :groupedPermissions="roleProps.groupedPermissions"
        :assignedPermissions="roleProps.assignedPermissions" />
          
  </AppLayout>
</template>