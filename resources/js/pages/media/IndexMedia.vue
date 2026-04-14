<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Upload, Trash2, ImageIcon, Search } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel,
  AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
  AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog'

interface MediaFileVm {
  id: number
  uuid: string
  url: string
  original_filename: string
  mime_type: string
  size_bytes: number
}

interface PaginationLink {
  url: string | null
  label: string
  active: boolean
}

interface PaginatedFiles {
  data: MediaFileVm[]
  links: PaginationLink[]
  total: number
  current_page: number
  last_page: number
}

const props = defineProps<{
  files: PaginatedFiles
  search: string
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Media Library', href: route('media.index') },
]

// ─── Upload ────────────────────────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null)
const uploadForm = useForm({ file: null as File | null })

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  const file = target.files?.[0] ?? null
  if (!file) return
  uploadForm.file = file
  uploadForm.post(route('media.store'), {
    forceFormData: true,
    onSuccess: () => {
      uploadForm.reset()
      if (fileInput.value) fileInput.value.value = ''
      toast.success('File uploaded.')
    },
    onError: (errors) => {
      toast.error(errors.file ?? 'Upload failed.')
    },
  })
}

// ─── Search ────────────────────────────────────────────────────────────────
const searchQuery = ref(props.search)

function doSearch() {
  router.get(route('media.index'), { search: searchQuery.value || undefined }, {
    preserveState: true,
    replace: true,
  })
}

// ─── Delete ────────────────────────────────────────────────────────────────
const deleteTarget = ref<MediaFileVm | null>(null)

function confirmDelete() {
  if (!deleteTarget.value) return
  router.delete(route('media.destroy', deleteTarget.value.id), {
    onSuccess: () => {
      deleteTarget.value = null
      toast.success('File deleted.')
    },
  })
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Media Library" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
      <!-- Header bar -->
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2">
          <ImageIcon class="h-5 w-5 text-muted-foreground" />
          <h1 class="text-xl font-semibold">
            Media Library
            <span class="text-muted-foreground text-sm font-normal">{{ files.total }} files</span>
          </h1>
        </div>

        <!-- Search -->
        <form class="flex gap-2" @submit.prevent="doSearch">
          <Input v-model="searchQuery" placeholder="Search files…" class="w-56" />
          <Button variant="outline" size="icon" type="submit">
            <Search class="h-4 w-4" />
          </Button>
        </form>

        <!-- Upload -->
        <div>
          <input ref="fileInput" type="file" class="hidden" accept="image/*" @change="onFileChange" />
          <Button size="sm" :disabled="uploadForm.processing" @click="fileInput?.click()">
            <Upload class="mr-1 h-4 w-4" />
            {{ uploadForm.processing ? 'Uploading…' : 'Upload' }}
          </Button>
        </div>
      </div>

      <!-- Grid -->
      <div
        v-if="files.data.length"
        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
      >
        <Card
          v-for="file in files.data"
          :key="file.id"
          class="group relative overflow-hidden"
        >
          <CardContent class="p-0">
            <img
              :src="file.url"
              :alt="file.original_filename"
              class="aspect-square w-full object-cover"
              loading="lazy"
            />
            <!-- Hover overlay -->
            <div class="absolute inset-0 flex flex-col justify-between bg-black/60 p-2 opacity-0 transition-opacity group-hover:opacity-100">
              <p class="line-clamp-2 text-[10px] text-white/80">{{ file.original_filename }}</p>
              <div class="flex items-center justify-between">
                <Badge variant="secondary" class="text-[10px]">{{ formatBytes(file.size_bytes) }}</Badge>
                <Button
                  variant="destructive"
                  size="icon"
                  class="h-6 w-6"
                  @click="deleteTarget = file"
                >
                  <Trash2 class="h-3 w-3" />
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Empty state -->
      <div
        v-else
        class="flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed py-16 text-muted-foreground"
      >
        <ImageIcon class="h-10 w-10" />
        <p>No files yet. Upload an image to get started.</p>
      </div>

      <!-- Pagination -->
      <div v-if="files.last_page > 1" class="flex justify-center gap-1">
        <template v-for="link in files.links" :key="link.label">
          <button
            type="button"
            :disabled="!link.url"
            class="inline-flex items-center justify-center rounded-md border px-3 py-1.5 text-sm transition-colors disabled:opacity-50"
            :class="{ 'bg-primary text-primary-foreground': link.active }"
            @click="link.url && router.visit(link.url)"
            v-html="link.label"
          ></button>
        </template>
      </div>
    </div>

    <!-- Delete confirmation -->
    <AlertDialog :open="deleteTarget !== null" @update:open="(val) => { if (!val) deleteTarget = null }">
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle>Delete file?</AlertDialogTitle>
          <AlertDialogDescription>
            "{{ deleteTarget?.original_filename }}" will be permanently removed from storage.
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
