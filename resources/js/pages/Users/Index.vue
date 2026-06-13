<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { router, Link, Head, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { columns } from '@/components/Users/columns';
import DataTable from '@/components/Users/DataTable.vue'
import StatsCards from '@/components/Stats/StatsCards.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Plus } from 'lucide-vue-next'
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
        users: any;
        filters?: { search?: string };
}>()

const page = usePage()
const props = page.props as any

const searchQuery = ref((page.props as any).filters?.search ?? '')
let searchDebounce: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (value) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    router.get(route('users.index'), { search: value || undefined }, {
      preserveState: true,
      preserveScroll: true,
      only: ['users', 'stats', 'filters'],
    })
  }, 300)
})

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
                <div class="space-y-6">
                        <!-- Hero Section -->
                        <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                <div class="space-y-1.5">
                                    <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">User management</span>
                                    <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Users</h1>
                                    <p class="max-w-xl text-sm leading-6 text-muted-foreground">Manage staff accounts, roles, and branch access.</p>
                                </div>
                                <Link :href="route('users.create')">
                                    <Button>
                                        <Plus class="mr-2 h-4 w-4" />
                                        Add User
                                    </Button>
                                </Link>
                            </div>
                        </div>
                        <StatsCards :cards="(props.stats ?? [
                                     { title: 'Total Users', value: users?.meta?.total ?? (users?.data?.length ?? 0), subtitle: 'All registered users', variant: 'primary' },
                                     { title: 'Active', value: (users?.data ?? []).filter((u: User) => !u.deleted_at).length, subtitle: 'Currently active', variant: 'accent' },
                                     { title: 'Inactive', value: (users?.meta?.total ?? (users?.data?.length ?? 0)) - ((users?.data ?? []).filter((u: User) => !u.deleted_at).length), subtitle: 'Deactivated accounts', variant: 'destructive' },
                                 ])" />
                                 <div class="px-1">
                                   <Input
                                     v-model="searchQuery"
                                     placeholder="Search by name or email…"
                                     class="h-9 max-w-sm"
                                     aria-label="Search users"
                                   />
                                 </div>
                                 <div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                                     <div class="p-4 sm:p-6">
                                         <DataTable :data="users?.data ?? []" :columns="columns" />
                                     </div>
                                 </div>

                         <div class="flex items-center justify-between">
                             <div class="text-sm text-muted-foreground">Showing {{ users?.data?.length ?? 0 }} of {{ users?.meta?.total ?? (users?.data?.length ?? 0) }}</div>
                             <div class="flex items-center gap-1">
                                 <button v-for="link in paginationLinks" :key="link.label" @click.prevent="goto(link)"
                                     class="inline-flex h-8 min-w-8 items-center justify-center rounded-md border border-black/8 bg-white/72 px-2.5 text-sm transition-colors hover:bg-woosoo-accent/10 dark:border-white/10 dark:bg-white/[0.04]"
                                     :class="{ 'border-woosoo-accent/40 bg-woosoo-accent/12 font-semibold text-woosoo-primary-dark dark:bg-woosoo-accent/15': link.active }" v-html="link.label">
                                 </button>
                             </div>
                         </div>
                </div>
    </AppLayout>
</template>
