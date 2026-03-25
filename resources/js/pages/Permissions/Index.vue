<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, ShieldCheck, Trash2 } from 'lucide-vue-next'

import AppLayout from '@/layouts/AppLayout.vue'
import InputError from '@/components/InputError.vue'
import { type BreadcrumbItem } from '@/types'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

interface PermissionRow {
  id: number
  name: string
  guard_name: 'web' | 'api' | string
  roles_count?: number
}

const props = defineProps<{
  title?: string
  description?: string
  permissions: PermissionRow[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Permissions',
    href: route('permissions.index'),
  },
]

const search = ref('')
const selectedIds = ref<number[]>([])

const createForm = useForm({
  name: '',
  guard_name: 'web',
})

const filteredPermissions = computed(() => {
  const query = search.value.trim().toLowerCase()
  if (!query) {
    return props.permissions
  }

  return props.permissions.filter((permission) => {
    const label = formatPermissionLabel(permission.name).toLowerCase()

    return permission.name.toLowerCase().includes(query)
      || label.includes(query)
      || permission.guard_name.toLowerCase().includes(query)
  })
})

const totalAssignedRoles = computed(() => {
  return props.permissions.reduce((total, permission) => total + (permission.roles_count ?? 0), 0)
})

const allFilteredSelected = computed(() => {
  return filteredPermissions.value.length > 0
    && filteredPermissions.value.every((permission) => selectedIds.value.includes(permission.id))
})

const selectedCount = computed(() => selectedIds.value.length)

function formatPermissionLabel(name: string) {
  return name
    .replace(/[._-]+/g, ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase())
}

function toggleSelection(permissionId: number, checked: boolean) {
  if (checked) {
    if (!selectedIds.value.includes(permissionId)) {
      selectedIds.value = [...selectedIds.value, permissionId]
    }
    return
  }

  selectedIds.value = selectedIds.value.filter((id) => id !== permissionId)
}

function toggleSelectAll(checked: boolean) {
  if (checked) {
    selectedIds.value = filteredPermissions.value.map((permission) => permission.id)
    return
  }

  selectedIds.value = []
}

function createPermission() {
  createForm.post(route('permissions.store'), {
    preserveScroll: true,
    onSuccess: () => {
      createForm.reset('name')
      createForm.setDefaults({ guard_name: createForm.guard_name })
      toast.success('Permission created.')
    },
    onError: () => {
      toast.error('Failed to create permission.')
    },
  })
}

function destroyPermission(permission: PermissionRow) {
  if (!window.confirm(`Delete permission '${permission.name}'? This removes it from any assigned roles.`)) {
    return
  }

  router.delete(route('permissions.destroy', permission.id), {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.value = selectedIds.value.filter((id) => id !== permission.id)
      toast.success('Permission deleted.')
    },
    onError: () => {
      toast.error('Failed to delete permission.')
    },
  })
}

function bulkDestroy() {
  if (!selectedIds.value.length) {
    return
  }

  if (!window.confirm(`Delete ${selectedIds.value.length} selected permission(s)? This cannot be undone.`)) {
    return
  }

  router.post(route('permissions.bulk-destroy'), {
    ids: selectedIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      selectedIds.value = []
      toast.success('Selected permissions deleted.')
    },
    onError: () => {
      toast.error('Failed to delete selected permissions.')
    },
  })
}
</script>

<template>
  <Head :title="title ?? 'Permissions'" :description="description" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="space-y-2">
          <h1 class="text-2xl font-bold tracking-tight">Permissions</h1>
          <p class="max-w-2xl text-muted-foreground">
            {{ description ?? 'Create and remove the granular permissions that roles can be assigned.' }}
          </p>
        </div>

        <div class="grid w-full gap-3 sm:grid-cols-3 lg:w-auto lg:min-w-[420px]">
          <Card>
            <CardHeader class="pb-2">
              <CardDescription>Total Permissions</CardDescription>
              <CardTitle class="text-2xl">{{ permissions.length }}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader class="pb-2">
              <CardDescription>Assigned Roles</CardDescription>
              <CardTitle class="text-2xl">{{ totalAssignedRoles }}</CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader class="pb-2">
              <CardDescription>Selected</CardDescription>
              <CardTitle class="text-2xl">{{ selectedCount }}</CardTitle>
            </CardHeader>
          </Card>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Plus class="h-4 w-4" />
              Create Permission
            </CardTitle>
            <CardDescription>
              Add a new permission key that can later be assigned to one or more roles.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form class="space-y-4" @submit.prevent="createPermission">
              <div class="grid gap-2">
                <Label for="permission-name">Permission Name</Label>
                <Input
                  id="permission-name"
                  v-model="createForm.name"
                  placeholder="orders.refund"
                />
                <p class="text-xs text-muted-foreground">
                  Use a stable dotted key such as `users.invite` or `reports.export.daily`.
                </p>
                <InputError :message="createForm.errors.name" />
              </div>

              <div class="grid gap-2">
                <Label for="permission-guard">Guard</Label>
                <Select v-model="createForm.guard_name">
                  <SelectTrigger id="permission-guard">
                    <SelectValue placeholder="Select a guard" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="web">web</SelectItem>
                    <SelectItem value="api">api</SelectItem>
                  </SelectContent>
                </Select>
                <InputError :message="createForm.errors.guard_name" />
              </div>

              <Button type="submit" :disabled="createForm.processing" class="w-full">
                <ShieldCheck class="mr-2 h-4 w-4" />
                Save Permission
              </Button>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader class="gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="space-y-1">
              <CardTitle>Permission Registry</CardTitle>
              <CardDescription>
                Review active permissions, search by key, and remove entries that are no longer valid.
              </CardDescription>
            </div>

            <div class="flex flex-col gap-3 sm:w-[320px]">
              <Input v-model="search" placeholder="Search permissions..." />
              <Button variant="destructive" :disabled="!selectedCount" @click="bulkDestroy">
                <Trash2 class="mr-2 h-4 w-4" />
                Delete Selected
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div class="overflow-x-auto rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead class="w-12">
                      <Checkbox
                        :model-value="allFilteredSelected"
                        @update:model-value="toggleSelectAll($event === true)"
                      />
                    </TableHead>
                    <TableHead>Permission</TableHead>
                    <TableHead>Label</TableHead>
                    <TableHead>Guard</TableHead>
                    <TableHead>Roles Using It</TableHead>
                    <TableHead class="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow v-for="permission in filteredPermissions" :key="permission.id">
                    <TableCell>
                      <Checkbox
                        :model-value="selectedIds.includes(permission.id)"
                        @update:model-value="toggleSelection(permission.id, $event === true)"
                      />
                    </TableCell>
                    <TableCell class="font-mono text-sm">{{ permission.name }}</TableCell>
                    <TableCell>{{ formatPermissionLabel(permission.name) }}</TableCell>
                    <TableCell>
                      <Badge variant="secondary">{{ permission.guard_name }}</Badge>
                    </TableCell>
                    <TableCell>{{ permission.roles_count ?? 0 }}</TableCell>
                    <TableCell class="text-right">
                      <Button variant="ghost" size="sm" @click="destroyPermission(permission)">
                        <Trash2 class="mr-2 h-4 w-4" />
                        Delete
                      </Button>
                    </TableCell>
                  </TableRow>
                  <TableRow v-if="!filteredPermissions.length">
                    <TableCell colspan="6" class="py-10 text-center text-sm text-muted-foreground">
                      No permissions match the current search.
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>