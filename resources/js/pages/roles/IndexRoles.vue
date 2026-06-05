<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Plus } from 'lucide-vue-next'
import { toast } from 'vue-sonner'

import AuthenticatedLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
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

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Roles', href: route('roles.index') },
]

const showCreateSheet = ref(false)

function handleCreateRole() {
  showCreateSheet.value = true
}
</script>

<template>
  <Head title="Roles & Permissions" />

  <AuthenticatedLayout :breadcrumbs="breadcrumbs">
    <div class="space-y-5">
      <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div class="space-y-3">
            <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
              Access control
            </span>
            <div>
              <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Roles & Permissions</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base">Manage system roles and their associated permissions.</p>
            </div>
          </div>
          <Button @click="handleCreateRole">
            <Plus class="mr-2 h-4 w-4" />
            New Role
          </Button>
        </div>
      </div>

      <div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
        <div class="p-4 sm:p-6">
          <DataTable :columns="columns" :data="roles.data" />
        </div>
      </div>
    </div>

    <Sheet v-model:open="showCreateSheet">
      <SheetContent side="right" class="sm:max-w-lg">
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
