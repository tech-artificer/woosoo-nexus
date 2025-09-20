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
import RolePermissions from '@/pages/accessibility/Index.vue'
const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Accessibility',
    href: route('accessibility.index'),
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
        <RolePermissions />
    </AppLayout>
</template>