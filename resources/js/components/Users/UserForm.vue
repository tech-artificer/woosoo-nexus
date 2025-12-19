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
  <div class="flex flex-col gap-3">
    <Separator class="my-0" />
    <form @submit.prevent="submit" class="p-4 flex flex-col gap-4">

      <div class="flex flex-col gap-3">
        <Label for="name">Name</Label>
        <Input type="text" v-model="form.name" placeholder="John Doe" />
        <InputError :message="form.errors.name" />
      </div>

      <div class="flex flex-col gap-3">
        <Label for="email">Email</Label>
        <Input type="email" v-model="form.email" placeholder="email@example" />
        <InputError :message="form.errors.email" />
      </div>

      <div class="flex flex-row justify-between gap-2">

        <div class="flex flex-col gap-2">
          <Label for="Role">Role</Label>
          <span class="text-xs text-muted-foreground">User can have multiple roles</span>
          <div class="flex flex-col gap-2">
            <div v-for="role in roles" :key="role.name" class="flex justify-start items-center gap-2">
              <Checkbox :id="`role-${role.name}`" :model-value="form.roles.includes(role.name)"
                @update:model-value="(checked: any) => toggleRole(role.name, checked)" />
              <label :for="`role-${role.name}`"
                class="text-xs leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70 capitalize">
                {{ role.name }}
              </label>
            </div>
          </div>
          <InputError :message="form.errors.roles" />
        </div>

        <div class="flex flex-col gap-2">
          <Label for="branch">Branch</Label>
          <span class="text-xs text-muted-foreground">User can have multiple branches</span>
          <div class="flex flex-col gap-2">
            <!-- branch checkboxes go here -->
          </div>
          <InputError :message="(form.errors as any).branches" />
        </div>

      </div>

    </form>
  </div>
  <SheetFooter>
    <div class="flex items-start flex-row gap-2 p-4">
      <SheetClose as-child>

        <Button type="button" variant="destructive" class="cursor-pointer ">
          Cancel
        </Button>

      </SheetClose>
      <Button type="button" @click.prevent="submit" variant="outline"
        class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark text-woosoo-primary-dark cursor-pointer"
        :disabled="form.processing">
        Save Changes
      </Button>
    </div>
  </SheetFooter>

</template>
                {{ branch.name }}
