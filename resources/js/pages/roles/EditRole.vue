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

interface Role {
  id: number
  name: string
  guard_name: string
  permissions: Permission[]
  permissions_count: number
  users_count: number
}

const props = defineProps<{
  role: Role
  permissions: Permission[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: route('roles.index') },
  { title: props.role.name, href: route('roles.show', props.role.id) },
  { title: 'Edit', href: '#' },
]
</script>

<template>
  <Head :title="`Edit: ${role.name}`" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="max-w-2xl mx-auto p-6 space-y-6">
      <div class="flex items-center gap-4">
        <Link :href="route('roles.show', role.id)">
          <Button variant="ghost" size="sm">
            <ChevronLeft class="h-4 w-4 mr-1" />
            Back
          </Button>
        </Link>
        <div>
          <h1 class="text-2xl font-bold tracking-tight">Edit Role</h1>
          <p class="text-muted-foreground">Update "{{ role.name }}" and its permissions</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Role Details</CardTitle>
          <CardDescription>Modify the role name or adjust permission assignments.</CardDescription>
        </CardHeader>
        <CardContent class="p-0">
          <RoleForm :role="role" @close="$inertia.visit(route('roles.show', role.id))" />
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
