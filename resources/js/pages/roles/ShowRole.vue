<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { type BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { ChevronLeft, Pencil, Trash2, Users, ShieldCheck } from 'lucide-vue-next'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import { computed } from 'vue'

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
  created_at: string
  updated_at: string
}

const props = defineProps<{
  role: Role
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: route('roles.index') },
  { title: props.role.name, href: '#' },
]

const groupedPermissions = computed<Record<string, Permission[]>>(() => {
  const groups: Record<string, Permission[]> = {}
  for (const perm of props.role.permissions ?? []) {
    const key = perm.name.split('.')[0] || 'general'
    ;(groups[key] ??= []).push(perm)
  }
  return groups
})

function formatAction(name: string): string {
  const parts = name.split('.')
  return parts[parts.length - 1].replace(/_/g, ' ')
}

function deleteRole() {
  router.delete(route('roles.destroy', props.role.id))
}
</script>

<template>
  <Head :title="role.name" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="max-w-2xl mx-auto p-6 space-y-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
          <Link :href="route('roles.index')">
            <Button variant="ghost" size="sm">
              <ChevronLeft class="h-4 w-4 mr-1" />
              Back
            </Button>
          </Link>
          <div>
            <h1 class="text-2xl font-bold tracking-tight">{{ role.name }}</h1>
            <p class="text-muted-foreground text-sm">Guard: {{ role.guard_name }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <Link :href="route('roles.edit', role.id)">
            <Button variant="outline" size="sm">
              <Pencil class="h-4 w-4 mr-1" />
              Edit
            </Button>
          </Link>
          <AlertDialog>
            <AlertDialogTrigger as-child>
              <Button variant="destructive" size="sm" :disabled="role.users_count > 0">
                <Trash2 class="h-4 w-4 mr-1" />
                Delete
              </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
              <AlertDialogHeader>
                <AlertDialogTitle>Delete "{{ role.name }}"?</AlertDialogTitle>
                <AlertDialogDescription>
                  This action cannot be undone. All permission assignments for this role will be removed.
                </AlertDialogDescription>
              </AlertDialogHeader>
              <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction @click="deleteRole" class="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                  Delete
                </AlertDialogAction>
              </AlertDialogFooter>
            </AlertDialogContent>
          </AlertDialog>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 gap-4">
        <Card>
          <CardContent class="flex items-center gap-3 pt-6">
            <ShieldCheck class="h-8 w-8 text-primary" />
            <div>
              <p class="text-2xl font-bold">{{ role.permissions_count }}</p>
              <p class="text-sm text-muted-foreground">Permissions</p>
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent class="flex items-center gap-3 pt-6">
            <Users class="h-8 w-8 text-primary" />
            <div>
              <p class="text-2xl font-bold">{{ role.users_count }}</p>
              <p class="text-sm text-muted-foreground">Users assigned</p>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Permissions -->
      <Card>
        <CardHeader>
          <CardTitle>Permissions</CardTitle>
          <CardDescription>
            All permissions granted to this role, grouped by resource.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="role.permissions && role.permissions.length > 0" class="space-y-4">
            <div
              v-for="(perms, resource) in groupedPermissions"
              :key="resource"
              class="space-y-2"
            >
              <p class="text-sm font-semibold capitalize">{{ resource }}</p>
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="perm in perms"
                  :key="perm.id"
                  variant="secondary"
                  class="capitalize"
                >
                  {{ formatAction(perm.name) }}
                </Badge>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-muted-foreground">No permissions assigned.</p>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
