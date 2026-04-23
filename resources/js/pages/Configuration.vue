<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ShieldCheck, Monitor, FileText, Wrench, DatabaseZap } from 'lucide-vue-next';

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

const configModules = [
    {
        title: 'Monitoring',
        description: 'Queue depth, failed jobs, print event health, and runtime metrics.',
        href: route('monitoring.index'),
        icon: Monitor,
        action: 'Open monitoring',
    },
    {
        title: 'Permissions',
        description: 'Manage access policies and role capabilities for admin operations.',
        href: route('permissions.index'),
        icon: ShieldCheck,
        action: 'Open permissions',
    },
    {
        title: 'Manual',
        description: 'Maintain operational docs and runbooks for admin, tablet, and relay teams.',
        href: route('manual.index'),
        icon: FileText,
        action: 'Open manual',
    },
    {
        title: 'Service Requests',
        description: 'Track and review support requests coming from ordering devices.',
        href: route('service-requests.index'),
        icon: Wrench,
        action: 'Open service requests',
    },
    {
        title: 'POS Database Connection',
        description: 'Configure the host, port, and credentials for the Krypton POS database.',
        href: route('pos-connection.index'),
        icon: DatabaseZap,
        action: 'Configure POS connection',
    },
]

</script>

<template>
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Configuration</h1>
                <p class="text-muted-foreground">System configuration settings</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="module in configModules" :key="module.title" class="transition-shadow hover:shadow-md">
                    <CardHeader class="pb-3">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <component :is="module.icon" class="h-4 w-4" />
                            {{ module.title }}
                        </CardTitle>
                        <CardDescription>{{ module.description }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Link :href="module.href" class="text-sm font-medium text-primary hover:underline">
                            {{ module.action }}
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
