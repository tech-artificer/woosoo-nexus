<script setup lang="ts">
/* eslint-disable @typescript-eslint/no-unused-vars */
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
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
  <AppLayout>
    <Head title="Branches" />

    <div class="p-6 space-y-6">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">Branches</h1>
        <p class="text-muted-foreground">
          Manage your branch locations and settings
        </p>
      </div>

      <DataTable 
        :data="branches" 
        @add="handleAdd"
        @edit="handleEdit"
      />

      <BranchForm
        v-model:open="showForm"
        :branch="editingBranch"
      />
    </div>
  </AppLayout>
</template>
