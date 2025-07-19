<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'POS Terminal Session',
        href: '/dashboard',
    },
];


import {
    Tabs,
    TabsContent,
    TabsList,
    TabsTrigger,
} from '@/components/ui/tabs'

defineProps<{
    title?: string;
    description?: string;
}>()

const tabItems = [
    {
        key: 'Terminal',
        value: 'terminal',
        content: 'Terminal',
    },
    {
        key: 'Terminal Session',
        value: 'terminal-session',
        content: 'Terminal Session',
    },
];

</script>

<template>

    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <Tabs  class="w-full" orientation="horizontal">
                <TabsList>
                    <TabsTrigger v-for="item in tabItems" :value="item.value" :key="item.key">
                        {{ item.key }}
                    </TabsTrigger>
                </TabsList>
                <TabsContent v-for="item in tabItems" :value="item.value" :key="item.key" class="p-2">
                   {{ item.content }}
                </TabsContent>
              
            </Tabs>
        </div>
    </AppLayout>
</template>
