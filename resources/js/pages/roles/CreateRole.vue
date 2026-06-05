<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { ChevronLeft } from 'lucide-vue-next'
import RoleForm from '@/components/Roles/RoleForm.vue'

interface Permission {
  id: number
  name: string
  guard_name: string
}

defineProps<{
  permissions: Permission[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: route('roles.index') },
  { title: 'Create Role', href: '#' },
]
</script>

<template>
  <Head title="Create Role" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-5">
      <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="space-y-3">
            <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
              Access control
            </span>
            <div>
              <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Create Role</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Define a new role and assign permissions.</p>
            </div>
          </div>
          <Link :href="route('roles.index')">
            <Button variant="ghost" size="sm">
              <ChevronLeft class="mr-1 h-4 w-4" />
              Back
            </Button>
          </Link>
        </div>
      </div>

      <Card class="rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
        <CardHeader>
          <CardTitle>Role Details</CardTitle>
          <CardDescription>Set a name and select the permissions this role should have.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <RoleForm @close="$inertia.visit(route('roles.index'))" />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
