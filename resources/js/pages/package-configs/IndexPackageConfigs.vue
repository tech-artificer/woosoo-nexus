<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, Pencil, Trash2, Package2 } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table'
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel,
  AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
  AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'

interface PackageConfigVm {
  id: number
  name: string
  description: string | null
  base_price: string
  is_active: boolean
  sort_order: number
}

defineProps<{ packages: PackageConfigVm[] }>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Package Configs', href: route('package-configs.index') },
]

// ─── Create ────────────────────────────────────────────────────────────────
const showCreate = ref(false)
const createForm = useForm({
  name: '', description: '', base_price: '', is_active: true, sort_order: 0,
})

function submitCreate() {
  createForm.post(route('package-configs.store'), {
    onSuccess: () => {
      showCreate.value = false
      createForm.reset()
      toast.success('Package created.')
    },
  })
}

// ─── Edit ──────────────────────────────────────────────────────────────────
const editingId = ref<number | null>(null)
const editForm = useForm({
  name: '', description: '', base_price: '', is_active: true, sort_order: 0,
})

function openEdit(pkg: PackageConfigVm) {
  editingId.value   = pkg.id
  editForm.name        = pkg.name
  editForm.description = pkg.description ?? ''
  editForm.base_price  = pkg.base_price
  editForm.is_active   = pkg.is_active
  editForm.sort_order  = pkg.sort_order
}

function submitEdit() {
  if (!editingId.value) return
  editForm.put(route('package-configs.update', editingId.value), {
    onSuccess: () => {
      editingId.value = null
      toast.success('Package updated.')
    },
  })
}

// ─── Delete ────────────────────────────────────────────────────────────────
const deleteTarget = ref<PackageConfigVm | null>(null)

function confirmDelete() {
  if (!deleteTarget.value) return
  router.delete(route('package-configs.destroy', deleteTarget.value.id), {
    onSuccess: () => {
      deleteTarget.value = null
      toast.success('Package deleted.')
    },
  })
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Package Configs" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Package2 class="h-5 w-5 text-muted-foreground" />
          <h1 class="text-xl font-semibold">Package Configs</h1>
        </div>
        <Button size="sm" @click="showCreate = true">
          <Plus class="mr-1 h-4 w-4" /> New Package
        </Button>
      </div>

      <!-- Table -->
      <Card>
        <CardContent class="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Price</TableHead>
                <TableHead>Order</TableHead>
                <TableHead>Status</TableHead>
                <TableHead class="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="pkg in packages" :key="pkg.id">
                <TableCell>
                  <div class="font-medium">{{ pkg.name }}</div>
                  <div v-if="pkg.description" class="text-xs text-muted-foreground truncate max-w-xs">{{ pkg.description }}</div>
                </TableCell>
                <TableCell>₱{{ pkg.base_price }}</TableCell>
                <TableCell>{{ pkg.sort_order }}</TableCell>
                <TableCell>
                  <Badge :variant="pkg.is_active ? 'default' : 'secondary'">
                    {{ pkg.is_active ? 'Active' : 'Inactive' }}
                  </Badge>
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" @click="openEdit(pkg)">
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="text-destructive" @click="deleteTarget = pkg">
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
              <TableRow v-if="!packages.length">
                <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                  No package configs yet.
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>

    <!-- Create dialog -->
    <Dialog :open="showCreate" @update:open="showCreate = $event">
      <DialogContent>
        <DialogHeader><DialogTitle>New Package</DialogTitle></DialogHeader>
        <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
          <div class="grid gap-1.5">
            <Label>Name</Label>
            <Input v-model="createForm.name" required />
          </div>
          <div class="grid gap-1.5">
            <Label>Description</Label>
            <Input v-model="createForm.description" />
          </div>
          <div class="grid gap-1.5">
            <Label>Price (₱)</Label>
            <Input v-model="createForm.base_price" type="number" step="0.01" min="0" required />
          </div>
          <div class="grid gap-1.5">
            <Label>Sort Order</Label>
            <Input v-model.number="createForm.sort_order" type="number" min="0" />
          </div>
          <div class="flex items-center gap-3">
            <Switch v-model:checked="createForm.is_active" />
            <Label>Active</Label>
          </div>
          <DialogFooter>
            <Button variant="outline" type="button" @click="showCreate = false">Cancel</Button>
            <Button type="submit" :disabled="createForm.processing">Create</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Edit dialog -->
    <Dialog :open="editingId !== null" @update:open="(val) => { if (!val) editingId = null }">
      <DialogContent>
        <DialogHeader><DialogTitle>Edit Package</DialogTitle></DialogHeader>
        <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
          <div class="grid gap-1.5">
            <Label>Name</Label>
            <Input v-model="editForm.name" required />
          </div>
          <div class="grid gap-1.5">
            <Label>Description</Label>
            <Input v-model="editForm.description" />
          </div>
          <div class="grid gap-1.5">
            <Label>Price (₱)</Label>
            <Input v-model="editForm.base_price" type="number" step="0.01" min="0" required />
          </div>
          <div class="grid gap-1.5">
            <Label>Sort Order</Label>
            <Input v-model.number="editForm.sort_order" type="number" min="0" />
          </div>
          <div class="flex items-center gap-3">
            <Switch v-model:checked="editForm.is_active" />
            <Label>Active</Label>
          </div>
          <DialogFooter>
            <Button variant="outline" type="button" @click="editingId = null">Cancel</Button>
            <Button type="submit" :disabled="editForm.processing">Save</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete confirmation -->
    <AlertDialog :open="deleteTarget !== null" @update:open="(val) => { if (!val) deleteTarget = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete package?</AlertDialogTitle>
          <AlertDialogDescription>
            "{{ deleteTarget?.name }}" and all its allowed-menu rules will be removed permanently.
          </AlertDialogDescription>
        </AlertDialogHeader>
        <AlertDialogFooter>
          <AlertDialogCancel>Cancel</AlertDialogCancel>
          <AlertDialogAction class="bg-destructive text-destructive-foreground" @click="confirmDelete">
            Delete
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  </AppLayout>
</template>
