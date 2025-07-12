<script setup lang="ts">
import { defineProps } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Head } from '@inertiajs/vue3'
import PlaceholderPattern from '../components/PlaceholderPattern.vue'

const props = defineProps<{
    title?: string
    description?: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
]
</script>

<template>
    <Head :title="props.title" :description="props.description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
            <div class="grid auto-rows-min gap-3 md:grid-cols-2">
           
                <div class="relative">
                    <h2 class="text-lg font-semibold">Service Workers</h2>
                    <ol class="flex flex-col gap-3 text-sm leading-normal mt-2 w-full">
                        <li>Start Reverb 
                            <code class="block">app:reverb-start</code>
                        </li>
                        <li>
                            Generate Device Codes 
                            <code class="block">devices:generate-codes</code>
                        </li>
                        <li>
                            Setup Payment Trigger 
                            <small class="block">Creates a log table and trigger to capture order updates</small>
                            <code class="block">pos:setup-payment-trigger</code>
                        </li>
                        <li>
                            Run Scheduled Jobs
                            <code class="block">schedule:work</code>
                        </li>
                    </ol>
                </div>
           
                <div class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <PlaceholderPattern />
                </div>
            </div>
            <div class="relative min-h-[100vh] flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border">
                <PlaceholderPattern />
            </div>
        </div>
    </AppLayout>
</template>

