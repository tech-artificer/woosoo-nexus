<script setup lang="ts">
import { onMounted } from 'vue'
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { toast } from 'vue-sonner'
import { Checkbox } from "@/components/ui/checkbox"
import { SheetClose, SheetFooter } from "@/components/ui/sheet"
import { Label } from '@/components/ui/label';
import type { Role, User } from '@/types/models';
import Separator from '../ui/separator/Separator.vue';

const page = usePage();
const roles = page.props.roles as Role[];

const props = defineProps<{
  user?: User
  formType: 'create' | 'edit'
}>();

const form = useForm({
  name: props.user?.name ?? '',
  email: props.user?.email ?? '',
  password: '',
  roles: [] as string[],
});

const toggleRole = (roleName: string | any, checked: boolean) => {
  if (checked && !form.roles.includes(roleName)) {
    form.roles.push(roleName)
  } else if (!checked) {
    form.roles = form.roles.filter(name => name !== roleName)
  }
}

function submit() {
  if (props.formType === 'create') {
    form.post(route('users.store'), {
      forceFormData: true,
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        toast('User Created:', {
          description: 'A new user has been created.',
          duration: 5000,
          position: 'top-right',
        });
        form.reset();
      },
    });
  } else {
    form.put(route('users.update', props.user?.id), {
      preserveScroll: true,
      onSuccess: (response: any) => {
        console.log(response);
        toast('User Updated:', {
          description: 'User details have been updated.',
          duration: 5000,
          position: 'top-right',
        });
      },
    });
  }
}

onMounted(() => {
  if (props.formType === 'edit' && props.user) {
    form.name = props.user.name
    form.email = props.user.email
    form.roles = props.user.roles.map((r: Role) => r.name) ?? []
  }
})
</script>

<template>
  <div class="flex flex-col gap-6">
    <Separator class="my-0" />
    <form @submit.prevent="submit" class="px-4 sm:px-6 py-6 flex flex-col gap-6">

      <div class="space-y-2">
        <Label for="name">Name <span class="text-destructive">*</span></Label>
        <Input type="text" v-model="form.name" placeholder="John Doe" required />
        <InputError :message="form.errors.name" />
      </div>

      <div class="space-y-2">
        <Label for="email">Email <span class="text-destructive">*</span></Label>
        <Input type="email" v-model="form.email" placeholder="email@example.com" required />
        <InputError :message="form.errors.email" />
      </div>

      <div class="flex flex-col lg:flex-row gap-6">

        <div class="flex-1 space-y-3" role="group" aria-labelledby="roles-label">
          <div>
            <Label id="roles-label" class="text-sm font-semibold">Roles</Label>
            <p class="text-sm text-muted-foreground mt-1">User can have multiple roles</p>
          </div>
          <div class="space-y-3 p-4 rounded-lg border bg-muted/30">
            <div v-for="role in roles" :key="role.name" class="flex items-center gap-3">
              <Checkbox :id="`role-${role.name}`" :model-value="form.roles.includes(role.name)"
                @update:model-value="(checked: any) => toggleRole(role.name, checked)" />
              <label :for="`role-${role.name}`"
                class="text-sm font-medium cursor-pointer select-none capitalize leading-tight">
                {{ role.name }}
              </label>
            </div>
          </div>
          <InputError :message="form.errors.roles" />
        </div>

        <div class="flex-1 space-y-3" role="group" aria-labelledby="branches-label">
          <div>
            <Label id="branches-label" class="text-sm font-semibold">Branches</Label>
            <p class="text-sm text-muted-foreground mt-1">User can have multiple branches</p>
          </div>
          <div class="space-y-3 p-4 rounded-lg border bg-muted/30">
            <!-- branch checkboxes go here -->
            <p class="text-sm text-muted-foreground italic">No branches available</p>
          </div>
          <InputError :message="(form.errors as any).branches" />
        </div>

      </div>

    </form>
  </div>
  <SheetFooter>
    <div class="flex items-center gap-3 px-4 sm:px-6 py-4 border-t bg-muted/30">
      <SheetClose as-child>
        <Button type="button" variant="outline" class="cursor-pointer">
          Cancel
        </Button>
      </SheetClose>
      <Button type="button" @click.prevent="submit"
        :disabled="form.processing">
        {{ form.processing ? 'Saving…' : (formType === 'create' ? 'Create User' : 'Save Changes') }}
      </Button>
    </div>
  </SheetFooter>

</template>
