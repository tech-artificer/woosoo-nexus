<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { useForm, usePage } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { toast } from 'vue-sonner'
import { Checkbox } from "@/components/ui/checkbox"
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

import {
  SheetClose,
  SheetFooter,
} from "@/components/ui/sheet"

import { Label } from '@/components/ui/label';
import { Role, Branch, User } from '@/types'
const page = usePage();
// Props
const roles = page.props.roles as Role[]
const branches = page.props.branches as Branch[]
// const showDialog = ref(false); // Dialog state

const props = defineProps<{
  user?: User
  formType: 'create' | 'edit'
}>();

const form = useForm({
  name: '',
  email: '',
  password: 'password',
  role_name: '' as string | any,
  branches: [] as number[]
});

const toggleBranch = (branchId: number, checked: boolean) => {
  if (checked && !form.branches.includes(branchId)) {
    form.branches.push(branchId)
  } else if (!checked) {
    form.branches = form.branches.filter(id => id !== branchId)
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
          // action: {
          //   label: 'Ok',
          //   variant: 'success',
          //   onClick: () => console.log('Ok'),
          // },
          duration: 5000,
          position: 'top-right',
        });
        form.reset();
      },
    });
  } else {
    form.put(route('users.update', props.user?.id), {
      preserveScroll: true,
      onSuccess: () => {
        toast('User Updated:', {
          description: 'User details have been updated.',
          // action: {
          //   label: 'Ok',
          //   variant: 'success',
          //   onClick: () => console.log('Ok'),
          // },
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
    form.role_name = props.user?.role ? props.user?.role : props.user?.role?.name
    form.branches = props.user.branches?.map((b: Branch) => b.id) ?? []
  }
})

</script>

<template>

  <div class="flex flex-col gap-3">

    <form @submit.prevent="submit" class="space-y-4 p-4">

      <div class="flex flex-col gap-3">
        <Label for="name" class="ml-1">Name</Label>
        <Input type="text" v-model="form.name" placeholder="John Doe" />
        <InputError :message="form.errors.name" />
      </div>

      <div class="flex flex-col gap-3">
        <Label for="email" class="ml-1">Email</Label>
        <Input type="email" v-model="form.email" placeholder="email@example" />
        <InputError :message="form.errors.email" />
      </div>

      <div class="flex flex-row gap-3">
        <div class="flex flex-col gap-3" v-if="formType == 'create'">
          <Label for="password" class="ml-1">Password</Label>
          <Input type="text" v-model="form.password" disabled
            class="w-50 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200" />
          <InputError :message="form.errors.password" />
        </div>
        <div class="flex flex-col gap-3">
          <Label for="Role" class="ml-1">Role</Label>

          <div v-if=" form.role_name == 'Owner'">
             <Select v-model="form.role_name" disabled>
              <SelectTrigger class="w-35 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <SelectValue placeholder="Choose a role" class="capitalize" />
              </SelectTrigger>
              <SelectContent class="bg-white dark:bg-gray-700 shadow-lg">
                <SelectGroup>
                  <SelectLabel>Roles</SelectLabel>
                  <SelectItem  :value="form.role_name" class="capitalize">
                    {{ form.role_name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>
          <div v-else>
            <Select v-model="form.role_name">
              <SelectTrigger class="w-35 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <SelectValue placeholder="Choose a role" class="capitalize" />
              </SelectTrigger>
              <SelectContent class="bg-white dark:bg-gray-700 shadow-lg">
                <SelectGroup>
                  <SelectLabel>Roles</SelectLabel>
                  <SelectItem v-for="role in roles" :key="role.id" :value="role.name" class="capitalize">
                    {{ role.name }}
                  </SelectItem>
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          <InputError :message="form.errors.role_name" />
        </div>

      </div>

      <div class="flex flex-col gap-3">

        <Label for="branch" class="ml-1">Branch</Label>


        <div v-for="branch in branches" :key="branch.id" class="flex items-center space-x-2">
          <Checkbox :id="`branch-${branch.id}`" :model-value="form.branches.includes(branch.id)"
            @update:model-value="(checked: any) => toggleBranch(branch.id, checked)" />
          <label :for="`branch-${branch.id}`"
            class="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70">
            {{ branch.name }}
          </label>
        </div>
         <InputError :message="form.errors.branches" />
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

<style scoped>
.error {
  color: red;
  font-size: 0.8em;
}
</style>
