<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { router } from '@inertiajs/core'
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue'
import { columns } from '@/components/Users/columns';
import DataTable from '@/components/Users/DataTable.vue'
import StatsCards from '@/components/Stats/StatsCards.vue'
import type { User } from '@/types/models';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'User Management',
        href: route('users.index'),
    },
];

defineProps<{
        title: string;
        description: string;
        users: any; // paginated response
}>()

const page = usePage()
const props = page.props as any

import { ref } from 'vue'
const rolesFromPage = (page.props && (page.props as any).roles) ? (page.props as any).roles : []

const paginationLinks = computed(() => {
    try {
        return (props.users?.links ?? []).filter((l: any) => l && l.url)
    } catch (e) {
        return []
    }
})

function goto(link: any) {
    if (!link || !link.url) return
    router.get(link.url, { preserveState: false })
}


</script>

<template>
    <Head :title="title" :description="description" />
   
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h1 class="text-2xl font-semibold text-gray-900">User Management</h1>
                <p class="text-sm text-gray-500 mt-1">Manage system users and their accounts</p>
            </div>

            <!-- Stats Section -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <StatsCards :cards="(props.stats ?? [
                    { title: 'Total Users', value: users?.meta?.total ?? users.data.length, subtitle: 'All registered users', variant: 'primary' },
                    { title: 'Active', value: (users?.data ?? []).filter(u => !u.deleted_at).length, subtitle: 'Currently active', variant: 'accent' },
                    { title: 'Inactive', value: (users?.meta?.total ?? users.data.length) - ((users?.data ?? []).filter(u => !u.deleted_at).length), subtitle: 'Deactivated accounts', variant: 'danger' },
                ])" />
            </div>

            <!-- Users Table Section -->
            <div class="bg-white rounded-lg shadow-sm p-6 space-y-4">
                <DataTable :data="users.data" :columns="columns" />

                <!-- Pagination -->
                <div class="flex items-center justify-between pt-4 border-t">
                    <div class="text-sm text-gray-600">
                        Showing {{ users.data.length }} of {{ users.meta?.total ?? users.data.length }}
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            v-for="link in paginationLinks"
                            :key="link.label"
                            @click.prevent="goto(link)"
                            class="px-3 py-1.5 text-sm rounded-md border transition-colors"
                            :class="link.active
                                ? 'font-semibold bg-primary text-primary-foreground hover:bg-primary/90'
                                : 'bg-white hover:bg-gray-50'"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
