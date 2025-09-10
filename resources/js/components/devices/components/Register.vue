<script setup lang="ts">
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { onMounted } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { UserPlus } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Role } from '@/types/models';
import { toast } from 'vue-sonner'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet"

import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

const page = usePage();
// Props
const roles = page.props.roles as Role[];
// const showDialog = ref(false); // Dialog state

const form = useForm({
  name: '',
  email: '',
  password: 'password',
  role: '',
});


function submit() {

  form.post(route('users.store'), {
    forceFormData: true,
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      // console.log('✅ Image uploaded successfully');
      toast('User Created:', {
        description: 'A new user has been created.',
        action: {
          label: 'Ok',
          variant: 'success',
          onClick: () => console.log('Ok'),
        },
        duration: 5000,
        position: 'top-right',
      });
      form.reset();
    },
    onError: () => {
      //   toast('An Error Occured', {
      //     description: 'Something Went Wrong!',
      //     // action: {
      //     //   label: 'Undo',
      //     //   onClick: () => console.log('Undo'),
      //     // },
      //     duration: 5000,
      //     position: 'top-right',
      //   });
    }
  });
}

onMounted(() => {

});


</script>

<template>
  <Sheet key="register">
    <SheetTrigger as-child>
      <!-- <TooltipProvider :delay-duration="100">
        <Tooltip>
            <TooltipTrigger as-child> -->
      <Button variant="outline" class="cursor-pointer">
        <UserPlus class="mr-2 h-4 w-4" /> New User
      </Button>
      <!-- </TooltipTrigger>
            <TooltipContent>
            <p>Add New User</p>
            </TooltipContent>
        </Tooltip>
        </TooltipProvider> -->
    </SheetTrigger>
    <SheetContent>
      <SheetHeader>
        <SheetTitle>New User</SheetTitle>
        <SheetDescription>
          Add a new user to the application. Please enter the name, email address, and select a role.
        </SheetDescription>
      </SheetHeader>
      <div class="flex flex-col gap-3">
        <form @submit.prevent="submit" method="post" class="space-y-4 p-4">

          <div class="flex flex-col gap-3">
            <Label for="name" class="text-woosoo-dark-gray ml-1">Name</Label>
            <Input type="text" v-model="form.name" placeholder="John Doe" />
            <InputError :message="form.errors.name" />
          </div>

          <div class="flex flex-col gap-3">
            <Label for="email" class="text-woosoo-dark-gray ml-1">Email</Label>
            <Input type="email" v-model="form.email" placeholder="email@example" />
            <InputError :message="form.errors.email" />
          </div>

          <div class="flex flex-row gap-3">
            <div class="flex flex-col gap-3">
              <Label for="password" class="text-woosoo-dark-gray ml-1">Password</Label>
              <Input type="text" v-model="form.password" disabled
                class="w-50 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200" />
              <InputError :message="form.errors.password" />
            </div>
            <div class="flex flex-col gap-3">
              <Label for="Role" class="text-woosoo-dark-gray ml-1">Role</Label>
              <Select v-model="form.role">
                <SelectTrigger class="w-35 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                  <SelectValue placeholder="Choose a role" />
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

              <InputError :message="form.errors.role" />
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
            class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark text-woosoo-primary-dark cursor-pointer "
            :disabled="form.processing">
            Save Changes
          </Button>
        </div>
      </SheetFooter>
    </SheetContent>
  </Sheet>
</template>

<style scoped>
.error {
  color: red;
  font-size: 0.8em;
}
</style>