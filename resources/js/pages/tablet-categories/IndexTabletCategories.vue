<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, Pencil, Trash2, LayoutList } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
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

interface CategoryVm {
  id: number
  name: string
  slug: string
  sort_order: number
  is_active: boolean
}

const props = defineProps<{ categories: CategoryVm[] }>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Tablet Categories', href: route('tablet-categories.index') },
]

// ─── Create ──────────────────────────────────────────────────────────────────
const showCreate = ref(false)
const createForm = useForm({ name: '', slug: '', sort_order: 0, is_active: true })

function submitCreate() {
  createForm.post(route('tablet-categories.store'), {
    onSuccess: () => {
      showCreate.value = false
      createForm.reset()
      toast.success('Category created.')
    },
  })
}

// ─── Edit ─────────────────────────────────────────────────────────────────────
const editingId = ref<number | null>(null)
const editForm = useForm({ name: '', slug: '', sort_order: 0, is_active: true })

function openEdit(cat: CategoryVm) {
  editingId.value = cat.id
  editForm.name       = cat.name
  editForm.slug       = cat.slug
  editForm.sort_order = cat.sort_order
  editForm.is_active  = cat.is_active
}

function submitEdit() {
  if (!editingId.value) return
  editForm.put(route('tablet-categories.update', editingId.value), {
    onSuccess: () => {
      editingId.value = null
      toast.success('Category updated.')
    },
  })
}

// ─── Delete ───────────────────────────────────────────────────────────────────
const deleteTarget = ref<CategoryVm | null>(null)

function confirmDelete() {
  if (!deleteTarget.value) return
  router.delete(route('tablet-categories.destroy', deleteTarget.value.id), {
    onSuccess: () => {
      deleteTarget.value = null
      toast.success('Category deleted.')
    },
  })
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Tablet Categories" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
      <!-- Header -->
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <LayoutList class="h-5 w-5 text-muted-foreground" />
          <h1 class="text-xl font-semibold">Tablet Categories</h1>
        </div>
        <Button size="sm" @click="showCreate = true">
          <Plus class="mr-1 h-4 w-4" /> New Category
        </Button>
      </div>

      <!-- Table -->
      <Card>
        <CardContent class="p-0">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Slug</TableHead>
                <TableHead>Order</TableHead>
                <TableHead>Status</TableHead>
                <TableHead class="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="cat in categories" :key="cat.id">
                <TableCell class="font-medium">{{ cat.name }}</TableCell>
                <TableCell class="text-muted-foreground text-sm">{{ cat.slug }}</TableCell>
                <TableCell>{{ cat.sort_order }}</TableCell>
                <TableCell>
                  <Badge :variant="cat.is_active ? 'default' : 'secondary'">
                    {{ cat.is_active ? 'Active' : 'Inactive' }}
                  </Badge>
                </TableCell>
                <TableCell class="text-right">
                  <div class="flex justify-end gap-1">
                    <Button variant="ghost" size="icon" @click="openEdit(cat)">
                      <Pencil class="h-4 w-4" />
                    </Button>
                    <Button variant="ghost" size="icon" class="text-destructive" @click="deleteTarget = cat">
                      <Trash2 class="h-4 w-4" />
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
              <TableRow v-if="!categories.length">
                <TableCell colspan="5" class="text-center text-muted-foreground py-8">
                  No categories yet. Falling back to built-in defaults.
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
        <DialogHeader>
          <DialogTitle>New Category</DialogTitle>
        </DialogHeader>
        <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
          <div class="grid gap-1.5">
            <Label for="c-name">Name</Label>
            <Input id="c-name" v-model="createForm.name" required />
          </div>
          <div class="grid gap-1.5">
            <Label for="c-slug">Slug <span class="text-muted-foreground text-xs">(auto-generated if empty)</span></Label>
            <Input id="c-slug" v-model="createForm.slug" placeholder="e.g. grilled-meats" />
          </div>
          <div class="grid gap-1.5">
            <Label for="c-order">Sort Order</Label>
            <Input id="c-order" v-model.number="createForm.sort_order" type="number" min="0" />
          </div>
          <div class="flex items-center gap-3">
            <Switch id="c-active" v-model:checked="createForm.is_active" />
            <Label for="c-active">Active</Label>
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
        <DialogHeader>
          <DialogTitle>Edit Category</DialogTitle>
        </DialogHeader>
        <form class="flex flex-col gap-4" @submit.prevent="submitEdit">
          <div class="grid gap-1.5">
            <Label>Name</Label>
            <Input v-model="editForm.name" required />
          </div>
          <div class="grid gap-1.5">
            <Label>Slug</Label>
            <Input v-model="editForm.slug" />
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
            <Button type="submit" :disabled="editForm.processing">Save Changes</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete confirmation -->
    <AlertDialog :open="deleteTarget !== null" @update:open="(val) => { if (!val) deleteTarget = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete category?</AlertDialogTitle>
          <AlertDialogDescription>
            "{{ deleteTarget?.name }}" and all its menu assignments will be removed. This cannot be undone.
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
