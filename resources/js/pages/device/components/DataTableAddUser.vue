<!-- resources/js/Pages/Menus/EditMenu.vue -->
<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { UserPlus } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Role } from '@/types/models';
import { toast } from 'vue-sonner'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip';
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';

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
const showDialog = ref(false); // Dialog state

const form = useForm({
  name: '',
  email: '',
  password: 'password',
  role: '',
});

// Open dialog
const openDialog = () => {
  showDialog.value = true;
};


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
      showDialog.value = false;
    },
    onError: () => {
      toast('An Error Occured', {
        description: 'Something Went Wrong!',
        // action: {
        //   label: 'Undo',
        //   onClick: () => console.log('Undo'),
        // },
        duration: 5000,
        position: 'top-right',
      });
    }
  });

}

onMounted(() => {

});

</script>
<template>

    <!-- {{ roles }} -->
  <Dialog v-model:open="showDialog">
    <TooltipProvider :delay-duration="100">
      <Tooltip>
        <TooltipTrigger as-child>
          <DialogTrigger as-child>
            <Button variant="outline" class="cursor-pointer" @click="openDialog">
              <UserPlus  />
            </Button>
          </DialogTrigger>
        </TooltipTrigger>
        <TooltipContent>
          <p>Add New User</p>
        </TooltipContent>
      </Tooltip>
    </TooltipProvider>

    <DialogContent class="sm:max-w-[600px]">
      <DialogHeader class="flex flex-col gap-2 justify-start">
     
        <DialogTitle>New User</DialogTitle>
        <DialogDescription>
          Add a new user
        </DialogDescription>
      </DialogHeader>
      <form @submit.prevent="submit" method="post" class="space-y-4">
        
        <div class="grid gap-2">
          <Label for="name" class="text-woosoo-dark-gray ml-1">Name</Label>
          <Input type="text" v-model="form.name" placeholder="John Doe" />
          <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
          <Label for="email" class="text-woosoo-dark-gray ml-1">Email</Label>
          <Input type="email" v-model="form.email" placeholder="email@example"/>
          <InputError :message="form.errors.email" />
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div class="grid gap-2">
            <Label for="password" class="text-woosoo-dark-gray ml-1">Password</Label>
            <Input type="text" v-model="form.password" disabled />
            <InputError :message="form.errors.password" />
            </div>
          <div class="grid gap-2">
             <Label for="Role" class="text-woosoo-dark-gray ml-1">Role</Label>
            <Select v-model="form.role">
                <SelectTrigger class="w-[250px]">
                <SelectValue placeholder="Select a role" />
                </SelectTrigger>
                <SelectContent>
                <SelectGroup>
                    <SelectLabel>Roles</SelectLabel>
                    <SelectItem v-for="role in roles" :key="role.id" :value="role.name">
                       {{ role.name }}
                    </SelectItem>
                </SelectGroup>
                </SelectContent>
            </Select>
          <InputError :message="form.errors.role" />
          </div>
        </div>

        <div class="flex items-center gap-2 flex-row-reverse mt-10">
          <Button type="button"
            @click.prevent="submit"
            variant="outline"
            class="hover:bg-woosoo-primary-light hover:text-woosoo-primary-dark text-woosoo-primary-dark cursor-pointer  w-20"
            :disabled="form.processing">
            Save 
          </Button>

          <DialogClose as-child>
            <Button type="button" variant="destructive" class="cursor-pointer w-20">
              Cancel
            </Button>
          </DialogClose>
        </div>
      </form>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
.error {
  color: red;
  font-size: 0.8em;
}
</style>
