<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Plus } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

import AuthenticatedLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
import { columns, type Role } from '@/components/Roles/columns'
import DataTable from '@/components/Roles/DataTable.vue'
import RoleForm from '@/components/Roles/RoleForm.vue'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

interface Props {
  roles: {
    data: Role[]
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  permissions: any[]
}

const props = defineProps<Props>()

const showCreateSheet = ref(false)

function handleCreateRole() {
  showCreateSheet.value = true
}
</script>

<template>
  <Head title="Roles & Permissions" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-2xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Roles & Permissions
          </h2>
          <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Manage system roles and their associated permissions
          </p>
        </div>
        <Button @click="handleCreateRole">
          <Plus class="mr-2 h-4 w-4" />
          New Role
        </Button>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <DataTable :columns="columns" :data="roles.data" />
          </div>
        </div>
      </div>
    </div>

    <Sheet v-model:open="showCreateSheet">
      <SheetContent>
        <SheetHeader>
          <SheetTitle>Create New Role</SheetTitle>
          <SheetDescription>
            Add a new role and assign permissions
          </SheetDescription>
        </SheetHeader>
        <RoleForm @close="showCreateSheet = false" />
      </SheetContent>
    </Sheet>
  </AuthenticatedLayout>
</template>
