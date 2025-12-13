<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { useForm, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'
import { toast } from 'vue-sonner'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import type { Role } from './DataTableRowActions.vue'

interface Permission {
  id: number
  name: string
  guard_name: string
}

interface Props {
  role?: Role
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

// Get permissions from Inertia page props
const page = usePage()
const permissions = computed(() => page.props.permissions as Permission[] || [])

const isEditing = computed(() => !!props.role?.id)

const form = useForm({
  name: props.role?.name || '',
  guard_name: props.role?.guard_name || 'web',
  permissions: props.role?.permissions?.map((p: any) => p.id) || [] as number[],
})

// Group permissions by prefix
const groupedPermissions = computed(() => {
  const perms = permissions.value
  if (!perms || perms.length === 0) return {}
  
  const groups: Record<string, Permission[]> = {}
  
  perms.forEach(permission => {
    const parts = permission.name.split(' ')
    const action = parts[0] // view, create, update, delete
    const resource = parts.slice(1).join(' ') // users, roles, etc.
    
    if (!groups[resource]) {
      groups[resource] = []
    }
    groups[resource].push(permission)
  })
  
  return groups
})

const togglePermission = (permissionId: number) => {
  const index = form.permissions.indexOf(permissionId)
  if (index > -1) {
    form.permissions.splice(index, 1)
  } else {
    form.permissions.push(permissionId)
  }
}

const toggleResourcePermissions = (resourcePerms: Permission[]) => {
  const allSelected = resourcePerms.every(p => form.permissions.includes(p.id))
  
  if (allSelected) {
    // Remove all permissions for this resource
    resourcePerms.forEach(p => {
      const index = form.permissions.indexOf(p.id)
      if (index > -1) form.permissions.splice(index, 1)
    })
  } else {
    // Add all permissions for this resource
    resourcePerms.forEach(p => {
      if (!form.permissions.includes(p.id)) {
        form.permissions.push(p.id)
      }
    })
  }
}

const isResourceFullySelected = (resourcePerms: Permission[]) => {
  return resourcePerms.every(p => form.permissions.includes(p.id))
}

const isResourcePartiallySelected = (resourcePerms: Permission[]) => {
  const selected = resourcePerms.filter(p => form.permissions.includes(p.id))
  return selected.length > 0 && selected.length < resourcePerms.length
}

const submit = () => {
  if (isEditing.value) {
    form.put(route('roles.update', props.role!.id), {
      onSuccess: () => {
        toast.success('Role updated successfully')
        emit('close')
      },
      onError: () => {
        toast.error('Failed to update role')
      }
    })
  } else {
    form.post(route('roles.store'), {
      onSuccess: () => {
        toast.success('Role created successfully')
        emit('close')
        form.reset()
      },
      onError: () => {
        toast.error('Failed to create role')
      }
    })
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="space-y-6 py-4">
    <div class="space-y-2">
      <Label for="name">Role Name</Label>
      <Input
        id="name"
        v-model="form.name"
        placeholder="Enter role name"
        :class="{ 'border-destructive': form.errors.name }"
      />
      <p v-if="form.errors.name" class="text-sm text-destructive">
        {{ form.errors.name }}
      </p>
    </div>

    <div class="space-y-2">
      <Label for="guard_name">Guard</Label>
      <Select v-model="form.guard_name">
        <SelectTrigger id="guard_name">
          <SelectValue placeholder="Select guard" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="web">Web</SelectItem>
          <SelectItem value="api">API</SelectItem>
        </SelectContent>
      </Select>
      <p v-if="form.errors.guard_name" class="text-sm text-destructive">
        {{ form.errors.guard_name }}
      </p>
    </div>

    <div v-if="permissions && permissions.length > 0" class="space-y-4">
      <Label>Permissions</Label>
      <div class="border rounded-lg p-4 max-h-[400px] overflow-y-auto space-y-4">
        <div
          v-for="(resourcePerms, resource) in groupedPermissions"
          :key="resource"
          class="space-y-2"
        >
          <div class="flex items-center space-x-2 pb-2 border-b">
            <Checkbox
              :id="`resource-${resource}`"
              :checked="isResourceFullySelected(resourcePerms)"
              :indeterminate="isResourcePartiallySelected(resourcePerms)"
              @update:checked="toggleResourcePermissions(resourcePerms)"
            />
            <Label
              :for="`resource-${resource}`"
              class="text-sm font-semibold capitalize cursor-pointer"
            >
              {{ resource }}
            </Label>
          </div>
          <div class="grid grid-cols-2 gap-2 pl-6">
            <div
              v-for="permission in resourcePerms"
              :key="permission.id"
              class="flex items-center space-x-2"
            >
              <Checkbox
                :id="`permission-${permission.id}`"
                :checked="form.permissions.includes(permission.id)"
                @update:checked="togglePermission(permission.id)"
              />
              <Label
                :for="`permission-${permission.id}`"
                class="text-sm cursor-pointer capitalize"
              >
                {{ permission.name.split(' ')[0] }}
              </Label>
            </div>
          </div>
        </div>
      </div>
      <p v-if="form.errors.permissions" class="text-sm text-destructive">
        {{ form.errors.permissions }}
      </p>
    </div>

    <div class="flex justify-end gap-2 pt-4">
      <Button type="button" variant="outline" @click="emit('close')" :disabled="form.processing">
        Cancel
      </Button>
      <Button type="submit" :disabled="form.processing">
        {{ form.processing ? 'Saving...' : (isEditing ? 'Update Role' : 'Create Role') }}
      </Button>
    </div>
  </form>
</template>
