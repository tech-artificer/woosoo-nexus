<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import { toast } from 'vue-sonner'
import type { Role } from './DataTableRowActions.vue'

interface Permission {
  id: number
  name: string
  label?: string
  guard_name: string
}

interface Props {
  role?: Role
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const page = usePage()
const permissions = computed(() => (page.props.permissions as Permission[]) || [])
const isEditing = computed(() => !!props.role?.id)

const form = useForm({
  name: props.role?.name || '',
  guard_name: 'web',
  permissions: (props.role?.permissions?.map((p: any) => p.id) || []) as number[],
})

// Sync form when the role prop changes (e.g. opening Edit for a different row)
watch(
  () => props.role,
  (newRole) => {
    form.name = newRole?.name || ''
    form.guard_name = 'web'
    form.permissions = (newRole?.permissions?.map((p: any) => p.id) || []) as number[]
    form.clearErrors()
  },
)

// Grouped permissions from Inertia shared data
const groupedPermissions = computed<Record<string, Permission[]>>(() => {
  const shared = (page.props as any).groupedPermissions as Record<string, any[]> | undefined
  if (shared && Object.keys(shared).length > 0) return shared

  const perms = permissions.value
  if (!perms?.length) return {}

  const groups: Record<string, Permission[]> = {}
  for (const perm of perms) {
    const key = perm.name.split('.')[0] || 'general'
    ;(groups[key] ??= []).push(perm)
  }
  return groups
})

const allPermissionIds = computed(() =>
  Object.values(groupedPermissions.value).flat().map((p) => p.id),
)

const allSelected = computed(
  () => allPermissionIds.value.length > 0 && allPermissionIds.value.every((id) => form.permissions.includes(id)),
)

const someSelected = computed(
  () => !allSelected.value && allPermissionIds.value.some((id) => form.permissions.includes(id)),
)

function toggleAll() {
  if (allSelected.value) {
    form.permissions = []
  } else {
    form.permissions = [...allPermissionIds.value]
  }
}

function togglePermission(permissionId: number) {
  const idx = form.permissions.indexOf(permissionId)
  if (idx > -1) {
    // Remove: create new array without this permission
    form.permissions = form.permissions.filter((id) => id !== permissionId)
  } else {
    // Add: create new array with this permission
    form.permissions = [...form.permissions, permissionId]
  }
}

function groupCheckedState(resourcePerms: Permission[]): boolean | 'indeterminate' {
  const total = resourcePerms.length
  const selected = resourcePerms.filter((p) => form.permissions.includes(p.id)).length
  if (selected === total) return true
  if (selected > 0) return 'indeterminate'
  return false
}

function toggleResourcePermissions(resourcePerms: Permission[]) {
  const allIn = resourcePerms.every((p) => form.permissions.includes(p.id))
  const resourceIds = new Set(resourcePerms.map((p) => p.id))
  
  if (allIn) {
    // Deselect all in group: create new array excluding these permissions
    form.permissions = form.permissions.filter((id) => !resourceIds.has(id))
  } else {
    // Select all in group: create new array including these permissions
    const toAdd = resourcePerms
      .filter((p) => !form.permissions.includes(p.id))
      .map((p) => p.id)
    form.permissions = [...form.permissions, ...toAdd]
  }
}

function formatLabel(perm: Permission): string {
  if (perm.label) return perm.label
  // derive from name: "orders.view" → "View"
  const parts = perm.name.split('.')
  return parts[parts.length - 1].replace(/_/g, ' ')
}

const submit = () => {
  if (isEditing.value) {
    form.put(route('roles.update', props.role!.id), {
      onSuccess: () => {
        toast.success('Role updated successfully')
        emit('close')
      },
      onError: () => toast.error('Failed to update role'),
    })
  } else {
    form.post(route('roles.store'), {
      onSuccess: () => {
        toast.success('Role created successfully')
        emit('close')
        form.reset()
      },
      onError: () => toast.error('Failed to create role'),
    })
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="flex flex-col gap-6 px-4 py-6 sm:px-6 h-full">
    <!-- Role name -->
    <div class="space-y-2">
      <Label for="name">Role Name <span class="text-destructive">*</span></Label>
      <Input
        id="name"
        v-model="form.name"
        placeholder="e.g. branch-manager"
        :class="{ 'border-destructive': form.errors.name }"
        autofocus
      />
      <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
    </div>

    <!-- Permissions -->
    <div v-if="permissions && permissions.length > 0" class="flex flex-col gap-4 min-h-0 flex-1">
      <!-- Select All row -->
      <div class="flex items-center justify-between">
        <Label class="text-sm font-semibold">Permissions</Label>
        <div class="flex items-center gap-2 text-sm">
          <Checkbox
            id="select-all-perms"
            :checked="someSelected ? 'indeterminate' : allSelected"
            @update:checked="toggleAll"
          />
          <label for="select-all-perms" class="cursor-pointer text-muted-foreground hover:text-foreground transition-colors select-none">
            {{ someSelected && !allSelected ? 'Select all' : allSelected ? 'Deselect all' : 'Select all' }}
          </label>
        </div>
      </div>

      <!-- Scrollable permission groups -->
      <div class="border rounded-lg overflow-y-auto flex-1 max-h-130 divide-y bg-muted/30">
        <div
          v-for="(resourcePerms, resource) in groupedPermissions"
          :key="resource"
          class="p-4 space-y-3 hover:bg-muted/50 transition-colors"
          role="group"
          :aria-labelledby="`group-label-${resource}`"
        >
          <!-- Group header checkbox -->
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Checkbox
                :id="`group-${resource}`"
                :checked="groupCheckedState(resourcePerms)"
                @update:checked="() => toggleResourcePermissions(resourcePerms)"
              />
              <label
                :for="`group-${resource}`"
                :id="`group-label-${resource}`"
                class="text-sm font-semibold capitalize cursor-pointer select-none"
              >
                {{ String(resource).charAt(0).toUpperCase() + String(resource).slice(1) }}
              </label>
            </div>
            <Badge variant="outline" class="text-xs font-medium">
              {{ resourcePerms.filter(p => form.permissions.includes(p.id)).length }}/{{ resourcePerms.length }}
            </Badge>
          </div>

          <!-- Individual permissions -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 pl-6 sm:pl-7">
            <div
              v-for="permission in resourcePerms"
              :key="permission.id"
              class="flex items-center gap-2"
            >
              <Checkbox
                :id="`perm-${permission.id}`"
                :checked="form.permissions.includes(permission.id)"
                @update:checked="() => togglePermission(permission.id)"
              />
              <label
                :for="`perm-${permission.id}`"
                class="text-sm cursor-pointer select-none capitalize leading-tight text-muted-foreground hover:text-foreground transition-colors break-words"
              >
                {{ formatLabel(permission) }}
              </label>
            </div>
          </div>
        </div>
      </div>

      <p v-if="form.errors.permissions" class="text-sm text-destructive">
        {{ form.errors.permissions }}
      </p>
    </div>

    <!-- Actions -->
    <div class="flex justify-end gap-3 pt-4 border-t">
      <Button type="button" variant="outline" @click="emit('close')" :disabled="form.processing">
        Cancel
      </Button>
      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Saving…' : (isEditing ? 'Update Role' : 'Create Role') }}
      </Button>
    </div>
  </form>
</template>
