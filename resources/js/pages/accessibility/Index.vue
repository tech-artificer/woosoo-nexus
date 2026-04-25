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
    onSuccess: () => {
      toast.success(`Successfully updated permissions for '${selectedRole.value}'.`);
    },
    onError: () => toast.error(`Failed to update permissions for '${selectedRole.value}'.`),
  });
};

// --- Methods ---
const handleTogglePermission = (permissionName: string, isChecked: boolean) => {
  if (isChecked) {
    if (!permissionsState.value.includes(permissionName)) {
      permissionsState.value.push(permissionName);
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
    <Card class="overflow-hidden border-black/8 bg-white/80 shadow-[0_24px_70px_-42px_rgba(37,37,37,0.32)] dark:border-white/10 dark:bg-white/[0.05]">
      <CardHeader class="border-b border-black/8 px-6 py-5 dark:border-white/10">
        <CardTitle class="text-2xl font-semibold tracking-tight text-foreground">
          Accessibility
        </CardTitle>
        <CardDescription class="max-w-2xl text-sm leading-6 text-muted-foreground">
          Manage what each role can access across the system.
        </CardDescription>
      </CardHeader>
      <CardContent class="space-y-8 px-6 py-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-end">
          <div class="space-y-2">
            <Label for="role-select" class="text-sm font-semibold text-foreground/80">
              Select a role
            </Label>
            <p class="text-sm text-muted-foreground">Choose a role to review and update its permissions.</p>
          </div>
          <Select v-model="selectedRole">
            <SelectTrigger id="role-select" class="w-full rounded-2xl border-black/10 bg-white/85 shadow-none md:w-[280px] dark:border-white/10 dark:bg-white/[0.06]">
              <SelectValue placeholder="Choose a role" />
            </SelectTrigger>
            <SelectContent class="rounded-2xl border-black/8 bg-white shadow-xl dark:border-white/10 dark:bg-[#1f1f1f]">
              <SelectGroup>
                <SelectLabel>Roles</SelectLabel>
                <SelectItem v-for="role in props.roles" :key="role.id" :value="role" class="capitalize">
                  {{ role.name }}
                </SelectItem>
              </SelectGroup>
            </SelectContent>
          </Select>
        </div>

        <div v-if="selectedRole" class="grid gap-8">
          <div v-for="(permissions, groupName) in props.groupedPermissions" :key="groupName" class="space-y-3">
            <h3 class="flex items-center gap-2 text-lg font-semibold capitalize text-foreground">
              <span class="h-2 w-2 rounded-full bg-[#f6b56d]"></span>
              {{ groupName }}
            </h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <div v-for="permission in permissions" :key="permission.id" class="flex items-center justify-between rounded-[22px] border border-black/8 bg-white/82 p-4 transition hover:border-[#f6b56d]/30 hover:shadow-[0_18px_44px_-34px_rgba(37,37,37,0.42)] dark:border-white/10 dark:bg-white/[0.06]">
                <div class="min-w-0 pr-4">
                  <Label :for="`permission-${permission.id}`" class="block truncate text-sm font-medium text-foreground">
                    {{ permission.label }}
                  </Label>
                  <p class="mt-1 text-xs text-muted-foreground">
                    {{ permission.guard_name }}
                  </p>
                </div>
                <Switch :id="`permission-${permission.id}`" :model-value="permissionsState.includes(permission.name)" @update:model-value="handleTogglePermission(permission.name, $event)" :disabled="isSubmitting" class="shrink-0 scale-110" />
              </div>
            </div>
          </div>

          <div class="sticky bottom-4 flex justify-end">
            <Button @click.prevent="savePermissions" :disabled="isSubmitting" class="rounded-2xl px-6 py-3">
              Save changes
            </Button>
          </div>
        </div>

        <div v-else class="rounded-[22px] border border-dashed border-black/10 bg-black/[0.02] p-6 text-sm leading-6 text-muted-foreground dark:border-white/10 dark:bg-white/[0.04]">
          Please select a role to manage its permissions.
        </div>
      </CardContent>
    </Card>
</template>
