<!-- Audit Fix (2026-04-06): admin UI for package CRUD and modifier mapping management. -->
<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'

interface PackageModifierVm {
  id?: number
  krypton_menu_id: number
  sort_order: number
}

interface PackageVm {
  id: number
  name: string
  krypton_menu_id: number
  is_active: boolean
  sort_order: number
  modifiers: PackageModifierVm[]
}

interface PackagesPageProps {
  title: string
  description: string
  packages: PackageVm[]
}

const props = defineProps<PackagesPageProps>()

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Packages',
    href: route('packages.index'),
  },
]

const editingId = ref<number | null>(null)

const form = useForm({
  name: '',
  krypton_menu_id: 0,
  is_active: true,
  sort_order: 0,
  modifier_ids_csv: '',
})

const orderedPackages = computed(() => {
  return [...(props.packages ?? [])].sort((a, b) => a.sort_order - b.sort_order)
})

function modifiersToCsv(modifiers: PackageModifierVm[]): string {
  return [...modifiers]
    .sort((a, b) => a.sort_order - b.sort_order)
    .map((m) => String(m.krypton_menu_id))
    .join(',')
}

function parseCsvToModifiers(csv: string): PackageModifierVm[] {
  const ids = csv
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
    .map((s) => Number(s))
    .filter((n) => Number.isFinite(n) && n > 0)

  return ids.map((id, index) => ({
    krypton_menu_id: id,
    sort_order: index,
  }))
}

function resetForm() {
  editingId.value = null
  form.reset()
  form.clearErrors()
  form.is_active = true
  form.krypton_menu_id = 0
  form.sort_order = 0
  form.modifier_ids_csv = ''
}

function editPackage(item: PackageVm) {
  editingId.value = item.id
  form.name = item.name
  form.krypton_menu_id = item.krypton_menu_id
  form.is_active = item.is_active
  form.sort_order = item.sort_order
  form.modifier_ids_csv = modifiersToCsv(item.modifiers ?? [])
}

function submit() {
  const payload = {
    name: form.name,
    krypton_menu_id: Number(form.krypton_menu_id),
    is_active: Boolean(form.is_active),
    sort_order: Number(form.sort_order),
    modifiers: parseCsvToModifiers(form.modifier_ids_csv),
  }

  if (editingId.value) {
    form.transform(() => payload).put(route('packages.update', editingId.value), {
      preserveScroll: true,
      onSuccess: () => resetForm(),
    })
    return
  }

  form.transform(() => payload).post(route('packages.store'), {
    preserveScroll: true,
    onSuccess: () => resetForm(),
  })
}

function deletePackage(item: PackageVm) {
  if (!confirm(`Delete package ${item.name}?`)) {
    return
  }

  router.delete(route('packages.destroy', item.id), {
    preserveScroll: true,
  })
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head :title="title" />

    <div class="space-y-6 p-6">
      <div>
        <h1 class="text-2xl font-semibold">{{ title }}</h1>
        <p class="text-sm text-muted-foreground">{{ description }}</p>
      </div>

      <section class="rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-medium">
          {{ editingId ? 'Edit Package' : 'Create Package' }}
        </h2>

        <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
          <div class="space-y-2">
            <Label for="package_name">Name</Label>
            <Input id="package_name" v-model="form.name" placeholder="Set Meal A" required />
            <p v-if="form.errors.name" class="text-sm text-red-600">{{ form.errors.name }}</p>
          </div>

          <div class="space-y-2">
            <Label for="krypton_menu_id">Krypton Menu ID</Label>
            <Input id="krypton_menu_id" v-model.number="form.krypton_menu_id" type="number" min="1" required />
            <p v-if="form.errors.krypton_menu_id" class="text-sm text-red-600">{{ form.errors.krypton_menu_id }}</p>
          </div>

          <div class="space-y-2">
            <Label for="sort_order">Sort Order</Label>
            <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
            <p v-if="form.errors.sort_order" class="text-sm text-red-600">{{ form.errors.sort_order }}</p>
          </div>

          <div class="space-y-2">
            <Label for="modifier_ids_csv">Modifier Krypton IDs (comma-separated)</Label>
            <Input id="modifier_ids_csv" v-model="form.modifier_ids_csv" placeholder="101,102,103" />
            <p v-if="form.errors.modifier_ids_csv" class="text-sm text-red-600">{{ form.errors.modifier_ids_csv }}</p>
          </div>

          <div class="md:col-span-2 flex items-center gap-3">
            <Switch id="is_active" :model-value="form.is_active" @update:model-value="(v) => form.is_active = Boolean(v)" />
            <Label for="is_active">Active</Label>
          </div>

          <div class="md:col-span-2 flex gap-2">
            <Button type="submit" :disabled="form.processing">
              {{ editingId ? 'Update Package' : 'Create Package' }}
            </Button>
            <Button type="button" variant="outline" @click="resetForm">Reset</Button>
          </div>
        </form>
      </section>

      <section class="rounded-lg border p-4">
        <h2 class="mb-4 text-lg font-medium">Configured Packages</h2>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b text-left">
                <th class="p-2">Name</th>
                <th class="p-2">Krypton Menu ID</th>
                <th class="p-2">Modifiers</th>
                <th class="p-2">Active</th>
                <th class="p-2">Sort</th>
                <th class="p-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in orderedPackages" :key="item.id" class="border-b align-top">
                <td class="p-2 font-medium">{{ item.name }}</td>
                <td class="p-2">{{ item.krypton_menu_id }}</td>
                <td class="p-2">{{ modifiersToCsv(item.modifiers ?? []) || '-' }}</td>
                <td class="p-2">{{ item.is_active ? 'Yes' : 'No' }}</td>
                <td class="p-2">{{ item.sort_order }}</td>
                <td class="p-2">
                  <div class="flex gap-2">
                    <Button size="sm" variant="outline" @click="editPackage(item)">Edit</Button>
                    <Button size="sm" variant="destructive" @click="deletePackage(item)">Delete</Button>
                  </div>
                </td>
              </tr>
              <tr v-if="orderedPackages.length === 0">
                <td class="p-3 text-muted-foreground" colspan="6">No packages configured yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
