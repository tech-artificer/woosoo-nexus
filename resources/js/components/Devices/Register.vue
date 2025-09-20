<script setup lang="ts">
import { Button } from "@/components/ui/button"
import { UserPlus } from 'lucide-vue-next';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet"
import UserForm from '@/components/users/UserForm.vue';
</script>

<template>
  <Sheet key="register">
    <SheetTrigger as-child>
      <Button variant="accent" class="cursor-pointer h-8 px-2 lg:px-3">
        <UserPlus  />
      </Button>
    </SheetTrigger>
    <SheetContent>
      <SheetHeader>
        <SheetTitle class="text-blue-600">New User</SheetTitle>
        <SheetDescription>
          Add a new user to the application. Please enter the name, email address, and select a role.
        </SheetDescription>
      </SheetHeader>

      <UserForm form-type="create" />
    </SheetContent>
  </Sheet>
</template>
