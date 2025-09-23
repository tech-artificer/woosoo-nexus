<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
// import Configuration from '@/components/configuration/Index.vue';

// const page = usePage();

// const roles = page.props.roles;
// const permissions = page.props.permissions;

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
           <!-- <pre>{{ roles }}</pre> -->
            <!-- <pre>{{ permissions }}</pre> -->
             <div class="flex flex-col gap-2">
                <!-- <Configuration /> -->
             </div>
        </div>
    </AppLayout>
</template>
