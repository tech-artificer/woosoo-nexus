<script setup lang="ts">
import { ref, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import {
  Card, CardContent, CardHeader, CardTitle, CardDescription,
} from '@/components/ui/card'
import {
  Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import type { Role, Permission } from '@/types/models'
import { toast } from 'vue-sonner'

interface GroupedPermissions {
  [key: string]: Permission[]
}

const props = defineProps<{
  roles: Role[]
  permissions: Permission[]
  groupedPermissions: GroupedPermissions
  assignedPermissions: { [key: string]: string[] }
}>()

const selectedRole = ref<Role | undefined>()
const permissionsState = ref<string[]>([])
const savedState = ref<string[]>([])
const search = ref('')
const pendingRole = ref<Role | undefined>()
const showUnsavedDialog = ref(false)

const form = useForm({
  permissions: [] as string[],
})

const isSubmitting = computed(() => form.processing)

const isDirty = computed(() => {
  const a = [...permissionsState.value].sort()
  const b = [...savedState.value].sort()
  return JSON.stringify(a) !== JSON.stringify(b)
})

const filteredGroupedPermissions = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return props.groupedPermissions
  const result: GroupedPermissions = {}
  for (const [groupName, perms] of Object.entries(props.groupedPermissions)) {
    const filtered = perms.filter(
      p =>
        p.name.toLowerCase().includes(q) ||
        (p.label ?? '').toLowerCase().includes(q) ||
        groupName.toLowerCase().includes(q),
    )
    if (filtered.length > 0) result[groupName] = filtered
  }
  return result
})

const allFilteredNames = computed(() =>
  Object.values(filteredGroupedPermissions.value).flat().map(p => p.name),
)

const allSelected = computed(
  () =>
    allFilteredNames.value.length > 0 &&
    allFilteredNames.value.every(name => permissionsState.value.includes(name)),
)

const someSelected = computed(
  () =>
    !allSelected.value &&
    allFilteredNames.value.some(name => permissionsState.value.includes(name)),
)

function groupCheckedState(groupName: string): boolean | 'indeterminate' {
  const perms = filteredGroupedPermissions.value[groupName] ?? []
  const selected = perms.filter(p => permissionsState.value.includes(p.name)).length
  if (perms.length > 0 && selected === perms.length) return true
  if (selected > 0) return 'indeterminate'
  return false
}

function toggleGroup(groupName: string) {
  const perms = filteredGroupedPermissions.value[groupName] ?? []
  const allIn = perms.every(p => permissionsState.value.includes(p.name))
  if (allIn) {
    const remove = new Set(perms.map(p => p.name))
    permissionsState.value = permissionsState.value.filter(n => !remove.has(n))
  } else {
    for (const p of perms) {
      if (!permissionsState.value.includes(p.name)) permissionsState.value.push(p.name)
    }
  }
}

function toggleAll() {
  if (allSelected.value) {
    const remove = new Set(allFilteredNames.value)
    permissionsState.value = permissionsState.value.filter(n => !remove.has(n))
  } else {
    for (const name of allFilteredNames.value) {
      if (!permissionsState.value.includes(name)) permissionsState.value.push(name)
    }
  }
}

function togglePermission(name: string) {
  const idx = permissionsState.value.indexOf(name)
  if (idx > -1) {
    permissionsState.value.splice(idx, 1)
  } else {
    permissionsState.value.push(name)
  }
}

function formatLabel(perm: Permission): string {
  if (perm.label) return perm.label
  const parts = perm.name.split('.')
  return parts[parts.length - 1].replace(/_/g, ' ')
}

function applyRoleSelection(role: Role) {
  selectedRole.value = role
  const assigned = props.assignedPermissions[role.name] ?? []
  permissionsState.value = [...assigned]
  savedState.value = [...assigned]
}

function selectRole(value: unknown) {
  if (!value || typeof value !== 'object') return
  const role = value as Role
  if (isDirty.value) {
    pendingRole.value = role
    showUnsavedDialog.value = true
    return
  }
  applyRoleSelection(role)
}

function discardAndSwitch() {
  if (pendingRole.value) {
    applyRoleSelection(pendingRole.value)
    pendingRole.value = undefined
  }
  showUnsavedDialog.value = false
}

function cancelSwitch() {
  pendingRole.value = undefined
  showUnsavedDialog.value = false
}

function savePermissions() {
  if (!selectedRole.value || !isDirty.value) return
  form.permissions = permissionsState.value
  form.post(route('roles.permissions.update', selectedRole.value.id), {
    onSuccess: () => {
      savedState.value = [...permissionsState.value]
      toast.success(`Permissions for '${selectedRole.value?.name}' updated.`)
    },
    onError: () => toast.error(`Failed to update permissions for '${selectedRole.value?.name}'.`),
  })
}
</script>

<template>
  <Card class="border-0">
    <CardHeader class="text-center border-b border-gray-200 dark:border-gray-700">
      <CardTitle class="text-3xl font-body font-semibold">
        Permission Registry
      </CardTitle>
      <CardDescription>
        Manage what each role can access across the system.
      </CardDescription>
    </CardHeader>
    <CardContent class="space-y-6 pt-4">

      <!-- Role selector -->
      <div class="flex flex-col md:flex-row md:items-center gap-4">
        <Label class="text-base font-semibold shrink-0">Select a Role</Label>
        <Select :model-value="selectedRole" @update:model-value="selectRole">
          <SelectTrigger class="w-full md:w-60">
            <SelectValue placeholder="Choose a role" />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              <SelectLabel>Roles</SelectLabel>
              <SelectItem
                v-for="role in props.roles"
                :key="role.id"
                :value="role"
                class="capitalize"
              >
                {{ role.name }}
              </SelectItem>
            </SelectGroup>
          </SelectContent>
        </Select>
        <span v-if="selectedRole && isDirty" class="text-sm text-amber-600 font-medium">
          Unsaved changes
        </span>
      </div>

      <!-- Permissions panel -->
      <div v-if="selectedRole" class="space-y-4">

        <!-- Toolbar: search + select-all -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
          <Input
            v-model="search"
            placeholder="Search permissions..."
            class="w-full sm:w-64 h-9"
          />
          <div class="flex items-center gap-2 text-sm ml-auto">
            <Checkbox
              id="select-all-perms"
              :checked="someSelected ? 'indeterminate' : allSelected"
              @update:checked="toggleAll"
            />
            <label for="select-all-perms" class="cursor-pointer text-muted-foreground select-none">
              {{ allSelected ? 'Deselect all' : 'Select all' }}
            </label>
          </div>
        </div>

        <!-- Grouped permission list -->
        <div class="border rounded-lg divide-y">
          <div
            v-for="(groupPerms, groupName) in filteredGroupedPermissions"
            :key="groupName"
            class="p-4 space-y-3"
          >
            <!-- Group header with checkbox -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <Checkbox
                  :id="`group-${groupName}`"
                  :checked="groupCheckedState(String(groupName))"
                  @update:checked="toggleGroup(String(groupName))"
                  :disabled="isSubmitting"
                />
                <label
                  :for="`group-${groupName}`"
                  class="text-sm font-semibold capitalize cursor-pointer select-none"
                >
                  {{ String(groupName).charAt(0).toUpperCase() + String(groupName).slice(1) }}
                </label>
              </div>
              <Badge variant="secondary" class="text-xs">
                {{ groupPerms.filter(p => permissionsState.includes(p.name)).length }}/{{ groupPerms.length }}
              </Badge>
            </div>

            <!-- Individual permission checkboxes -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 pl-6">
              <div
                v-for="permission in groupPerms"
                :key="permission.id"
                class="flex items-center gap-2"
              >
                <Checkbox
                  :id="`perm-${permission.id}`"
                  :checked="permissionsState.includes(permission.name)"
                  @update:checked="togglePermission(permission.name)"
                  :disabled="isSubmitting"
                />
                <label
                  :for="`perm-${permission.id}`"
                  class="text-sm cursor-pointer select-none capitalize leading-tight"
                >
                  {{ formatLabel(permission) }}
                </label>
              </div>
            </div>
          </div>

          <!-- Empty search state -->
          <div
            v-if="Object.keys(filteredGroupedPermissions).length === 0"
            class="py-10 text-center text-sm text-muted-foreground"
          >
            No permissions match "{{ search }}"
          </div>
        </div>

        <!-- Save bar -->
        <div class="sticky bottom-4 flex justify-end">
          <Button
            @click.prevent="savePermissions"
            :disabled="isSubmitting || !isDirty"
          >
            {{ isSubmitting ? 'Saving…' : 'Save Changes' }}
          </Button>
        </div>
      </div>

      <!-- Empty state: no role selected -->
      <div
        v-else
        class="flex flex-col items-center justify-center py-16 text-center text-muted-foreground gap-3"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
        </svg>
        <p class="text-sm">Select a role above to view and manage its permissions.</p>
      </div>

    </CardContent>
  </Card>

  <!-- Unsaved changes guard dialog -->
  <AlertDialog v-model:open="showUnsavedDialog">
    <AlertDialogContent>
      <AlertDialogHeader>
        <AlertDialogTitle>Unsaved Changes</AlertDialogTitle>
        <AlertDialogDescription>
          You have unsaved permission changes for "{{ selectedRole?.name }}". Switching roles will discard them.
        </AlertDialogDescription>
      </AlertDialogHeader>
      <AlertDialogFooter>
        <AlertDialogCancel @click="cancelSwitch">Stay</AlertDialogCancel>
        <AlertDialogAction class="bg-destructive hover:bg-destructive/90" @click="discardAndSwitch">
          Discard & Switch
        </AlertDialogAction>
      </AlertDialogFooter>
    </AlertDialogContent>
  </AlertDialog>
</template>