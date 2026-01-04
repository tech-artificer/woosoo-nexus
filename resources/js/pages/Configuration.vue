<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Configuration',
        href: '/configuration',
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
        <div class="flex h-full flex-1 flex-col gap-4 rounded p-6">
            <div class="flex flex-col gap-2">
            </div>
        </div>
    </AppLayout>
</template>
