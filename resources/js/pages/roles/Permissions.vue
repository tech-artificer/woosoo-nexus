<script setup lang="ts">
import { ref, watch, computed, reactive } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
  Card, CardContent, CardHeader, CardTitle, CardDescription
} from '@/components/ui/card';
import {
  Select, SelectContent, SelectGroup, SelectItem, SelectLabel, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';
import { Role, Permission } from '@/types/models';
import { toast } from 'vue-sonner';

import { usePage } from '@inertiajs/vue3';
const page = usePage();

interface GroupedPermissions {
  [key: string]: Permission[];
}


const props = reactive({
  roles: page.props.roles as Role[],
  permissions: page.props.permissions as Permission[],
  groupedPermissions: page.props.groupedPermissions as GroupedPermissions,
  assignedPermissions: page.props.assignedPermissions as { [key: string]: string[] },
});

const selectedRole = ref<Role>();   // role name
const permissionsState = ref<string[]>([]);      // array of permission names

const form = useForm({
  permissions: [] as string[],
});

const isSubmitting = computed(() => form.processing);

const savePermissions = () => {
  if (!selectedRole.value) return;

  form.permissions = permissionsState.value;
  form.post(route('roles.permissions.update', selectedRole.value.id), {
    onSuccess: (response: any) => {
      toast.success(`Successfully updated permissions for '${selectedRole.value}'.`);
      console.log(response);
    },
    onError: () => toast.error(`Failed to update permissions for '${selectedRole.value}'.`),
  });
};

// --- Methods ---
const handleTogglePermission = (permissionName: string, isChecked: boolean) => {
  console.log(isChecked);
  if (isChecked) {
    if (!permissionsState.value.includes(permissionName)) {
      permissionsState.value.push(permissionName);
      console.log(permissionName);
    }
  } else {
    permissionsState.value = permissionsState.value.filter(p => p !== permissionName);
  }
};

// --- Watchers ---
watch(selectedRole, (newRoleName) => {
  if (newRoleName) {
    // load assigned permissions from props
    const assigned = props.assignedPermissions[newRoleName.name] || [];
    permissionsState.value = [...assigned];
  } else {
    permissionsState.value = [];
  }
});
</script>

<template>
    <Card class="border-0">
      <CardHeader class="text-center6 border-b border-gray-200 dark:border-gray-700">
        <CardTitle class="text-3xl font-body font-semibold border-0">
          Roles & Permissions
        </CardTitle>
        <CardDescription class="text-muted-foreground">
          Manage what each role can access across the system.
        </CardDescription>
      </CardHeader>
      <!-- Content -->
      <CardContent class="space-y-8 pt-3">
        <!-- Role Selection -->
        <div class="flex flex-col md:flex-row md:items-center gap-4">
          <Label htmlFor="role-select" class="text-base font-semibold text-gray-700 dark:text-gray-300">
            Select a Role
          </Label>
          <Select v-model="selectedRole">
            <SelectTrigger
              class="w-full md:w-[240px] bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg">
              <SelectValue placeholder="Choose a role" />
            </SelectTrigger>
            <SelectContent class="bg-white dark:bg-gray-700 rounded-lg shadow-lg">
              <SelectGroup>
                <SelectLabel>Roles</SelectLabel>
                <SelectItem v-for="role in props.roles" :key="role.id" :value="role" class="capitalize">
                  {{ role.name }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>
        <!-- Permissions -->
        <div v-if="selectedRole" class="grid gap-8">
          <div v-for="(permissions, groupName) in props.groupedPermissions" :key="groupName" class="space-y-3">
            <!-- Section header -->
            <h3 class="text-lg font-semibold capitalize text-gray-700 dark:text-gray-200 flex items-center">
              <span class="w-2 h-2 rounded-full bg-accent mr-2"></span>
              {{ groupName }}
            </h3>

            <!-- Permission switches -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
              <div v-for="permission in permissions" :key="permission.id"
                class="flex flex-row items-center justify-between p-4 bg-gray-100 dark:bg-gray-700 rounded-xl border border-transparent hover:border-woosoo-primary-light transition">
                <div>
                  <Label :for="`permission-${permission.id}`"
                    class="text-sm font-medium text-gray-700 dark:text-gray-200 capitalize">
                    {{ permission.label }}
                  </Label>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ permission.guard_name }}
                  </p>
                </div>
                <Switch :id="`permission-${permission.id}`" :model-value="permissionsState.includes(permission.name)"
                  @update:model-value="handleTogglePermission(permission.name, $event)" :disabled="isSubmitting"
                  class="scale-110" />
              </div>
            </div>
          </div>

          <!-- Save button -->
          <div class="sticky bottom-4 flex justify-end">
            <Button @click.prevent="savePermissions" :disabled="isSubmitting" variant="default"
              class="px-6 py-3 rounded cursor-pointer border-1 border-woosoo-accent bg-woosoo-primary-light hover:bg-woosoo-primary-dark hover:text-woosoo-white transition">
              <!-- 💾 Save Changes -->
              Save Changes
            </Button>
          </div>
        </div>
        <!-- No role selected -->
        <div v-else class="">
          <p class=""> Please select a role to manage its permissions.</p>
        </div>
      </CardContent>
    </Card>
</template>
