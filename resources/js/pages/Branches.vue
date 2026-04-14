<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import type { Branch } from '@/types/models';
import DataTable from '@/components/Branches/DataTable.vue';
import BranchForm from '@/components/Branches/BranchForm.vue';
import { ref } from 'vue';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Manage Branches',
        href: route('branches.index'),
    },
];

defineProps<{
    title?: string,
    description?: string,
    branches: Branch[],
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
    <Head :title="title" :description="description" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 space-y-6">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Branches</h1>
                <p class="text-muted-foreground">Manage your branch locations and settings</p>
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
