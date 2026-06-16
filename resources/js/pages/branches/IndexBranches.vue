<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { type BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Plus } from 'lucide-vue-next'
import DataTable from '@/components/Branches/DataTable.vue'
import BranchForm from '@/components/Branches/BranchForm.vue'

interface Branch {
  id: number
  branch_uuid: string
  name: string
  location: string | null
  devices_count?: number
  users_count?: number
  created_at: string
  updated_at: string
  deleted_at: string | null
}

const props = defineProps<{
  branches: Branch[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Branches',
    href: '/branches',
  },
]

const showForm = ref(false)
const editingBranch = ref<Branch | null>(null)

const handleAdd = () => {
  editingBranch.value = null
  showForm.value = true
}

const handleEdit = (branch: Branch) => {
  editingBranch.value = branch
  showForm.value = true
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Branches" />

    <div class="space-y-5">
      <!-- Hero -->
      <div class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-5 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div class="space-y-1.5">
            <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">Configuration</span>
            <h1 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">Branches</h1>
            <p class="max-w-xl text-sm leading-6 text-muted-foreground">Manage branch locations, their device allocations, and associated users.</p>
          </div>
          <Button @click="handleAdd">
            <Plus class="mr-2 h-4 w-4" />
            Add Branch
          </Button>
        </div>
      </div>

      <!-- DataTable -->
      <div class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
        <div class="p-4 sm:p-6">
          <DataTable
            :data="branches"
            @add="handleAdd"
            @edit="handleEdit"
          />
        </div>
      </div>

      <BranchForm
        v-model:open="showForm"
        :branch="editingBranch"
      />
    </div>
  </AppLayout>
</template>
