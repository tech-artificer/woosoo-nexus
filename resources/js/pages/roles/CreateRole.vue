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
    <div class="max-w-2xl mx-auto p-6 space-y-6">
      <div class="flex items-center gap-4">
        <Link :href="route('roles.index')">
          <Button variant="ghost" size="sm">
            <ChevronLeft class="h-4 w-4 mr-1" />
            Back
          </Button>
        </Link>
        <div>
          <h1 class="text-2xl font-bold tracking-tight">Create Role</h1>
          <p class="text-muted-foreground">Define a new role and assign permissions</p>
        </div>
      </div>

      <Card>
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
