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
        <!-- <pre> {{ users }} </pre> -->
                <div class="space-y-6">
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight">User Management</h1>
                            <p class="text-muted-foreground">Manage system users and their accounts</p>
                        </div>
                        <!-- Filters moved into Users DataTable toolbar -->
                        <StatsCards :cards="(props.stats ?? [
                                     { title: 'Total Users', value: users?.meta?.total ?? users.data.length, subtitle: 'All registered users', variant: 'primary' },
                                     { title: 'Active', value: (users?.data ?? []).filter(u => !u.deleted_at).length, subtitle: 'Currently active', variant: 'accent' },
                                     { title: 'Inactive', value: (users?.meta?.total ?? users.data.length) - ((users?.data ?? []).filter(u => !u.deleted_at).length), subtitle: 'Deactivated accounts', variant: 'danger' },
                                 ])" />
                                 <DataTable :data="users.data" :columns="columns" />

                         <div class="flex items-center justify-between">
                             <div class="text-sm text-muted-foreground">Showing {{ users.data.length }} of {{ users.meta?.total ?? users.data.length }}</div>
                             <div class="flex items-center gap-1">
                                 <button v-for="link in paginationLinks" :key="link.label" @click.prevent="goto(link)"
                                     class="px-3 py-1.5 text-sm rounded-md border bg-background hover:bg-accent transition-colors" :class="{ 'font-semibold bg-primary text-primary-foreground hover:bg-primary/90': link.active }" v-html="link.label">
                                 </button>
                             </div>
                         </div>
                </div>
    </AppLayout>
</template>
