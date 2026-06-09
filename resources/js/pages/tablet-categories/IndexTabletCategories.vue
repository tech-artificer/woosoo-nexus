<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, Pencil, Trash2, GripVertical, Star, X, Search } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import {
    AlertDialog, AlertDialogAction, AlertDialogCancel,
    AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
    AlertDialogHeader, AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'

interface CategoryMenu {
    id: number
    krypton_menu_id: number
    name: string
    is_featured: boolean
    sort_order: number
}

interface CategoryVm {
    id: number
    name: string
    slug: string
    sort_order: number
    is_active: boolean
    menu_count: number
    menus: CategoryMenu[]
}

interface UnattachedMenu {
    id: number
    name: string
}

const props = defineProps<{
    categories: CategoryVm[]
    unattachedMenus?: UnattachedMenu[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tablet Categories', href: route('tablet-categories.index') },
]

const selectedId = ref<number | null>(props.categories[0]?.id ?? null)
const selectedCategory = computed(() =>
    props.categories.find((c) => c.id === selectedId.value) ?? null,
)

// ─── Category Drag Reorder ─────────────────────────────────────────────────
const dragOverId = ref<number | null>(null)
let draggingId: number | null = null

function onDragStart(id: number) { draggingId = id }
function onDragEnd() {
    draggingId = null
    dragOverId.value = null
}
function onDragOver(e: DragEvent, id: number) {
    e.preventDefault()
    dragOverId.value = id
}

function onDrop(targetId: number) {
    if (!draggingId || draggingId === targetId) return
    const ordered = [...props.categories].sort((a, b) => a.sort_order - b.sort_order)
    const fromIdx = ordered.findIndex((c) => c.id === draggingId)
    const toIdx   = ordered.findIndex((c) => c.id === targetId)
    if (fromIdx === -1 || toIdx === -1) return
    const [moved] = ordered.splice(fromIdx, 1)
    ordered.splice(toIdx, 0, moved)
    dragOverId.value = null
    router.put(route('tablet-categories.reorder'), {
        ids: ordered.map((c) => c.id),
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Order saved.'),
        onError: () => toast.error('Failed to save order.'),
    })
}

// ─── Create category ──────────────────────────────────────────────────────
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

// ─── Edit category ────────────────────────────────────────────────────────
const editingId = ref<number | null>(null)
const editForm = useForm({ name: '', slug: '', sort_order: 0, is_active: true })

function openEdit(cat: CategoryVm) {
    editingId.value    = cat.id
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

// ─── Delete category ──────────────────────────────────────────────────────
const deleteTarget = ref<CategoryVm | null>(null)

function executeDelete() {
    if (!deleteTarget.value) return
    const id = deleteTarget.value.id
    router.delete(route('tablet-categories.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedId.value === id) {
                selectedId.value = props.categories.find((c) => c.id !== id)?.id ?? null
            }
            deleteTarget.value = null
            toast.success('Category deleted.')
        },
    })
}

// ─── Toggle category active ───────────────────────────────────────────────
function toggleActive(cat: CategoryVm) {
    router.put(route('tablet-categories.update', cat.id), {
        name: cat.name,
        slug: cat.slug,
        sort_order: cat.sort_order,
        is_active: !cat.is_active,
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success(`${cat.name} ${!cat.is_active ? 'activated' : 'deactivated'}.`),
    })
}

// ─── Detach menu ──────────────────────────────────────────────────────────
const detachTarget = ref<{ catId: number; menuId: number; name: string } | null>(null)

function confirmDetach(cat: CategoryVm, menu: CategoryMenu) {
    detachTarget.value = { catId: cat.id, menuId: menu.krypton_menu_id, name: menu.name }
}

function executeDetach() {
    if (!detachTarget.value) return
    const { catId, menuId } = detachTarget.value
    detachTarget.value = null
    router.delete(route('tablet-categories.menus.detach', { tabletCategory: catId, menuId }), {
        preserveScroll: true,
        onSuccess: () => toast.success('Menu detached.'),
        onError: () => toast.error('Failed to detach menu.'),
    })
}

// ─── Toggle featured ─────────────────────────────────────────────────────
function toggleFeatured(cat: CategoryVm, menu: CategoryMenu) {
    router.post(route('tablet-categories.menus.featured', { tabletCategory: cat.id, menuId: menu.krypton_menu_id }), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(`${menu.name} ${!menu.is_featured ? 'featured' : 'unfeatured'}.`),
    })
}

// ─── Attach menus ─────────────────────────────────────────────────────────
const showAttach = ref(false)
const attachSearch = ref('')
const selectedAttachIds = ref<number[]>([])

const filteredUnattached = computed(() => {
    const needle = attachSearch.value.trim().toLowerCase()
    return (props.unattachedMenus ?? []).filter((m) =>
        !needle || m.name.toLowerCase().includes(needle),
    )
})

function toggleAttachSelection(id: number) {
    const idx = selectedAttachIds.value.indexOf(id)
    if (idx === -1) {
        selectedAttachIds.value.push(id)
    } else {
        selectedAttachIds.value.splice(idx, 1)
    }
}

function submitAttach() {
    if (!selectedCategory.value || selectedAttachIds.value.length === 0) return
    router.post(
        route('tablet-categories.menus.attach', selectedCategory.value.id),
        { menu_ids: selectedAttachIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                showAttach.value = false
                selectedAttachIds.value = []
                attachSearch.value = ''
                toast.success('Menu(s) attached.')
            },
            onError: () => toast.error('Failed to attach menus.'),
        },
    )
}
</script>

<template>
    <Head title="Tablet Categories" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero header -->
            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#f6b56d]/10 via-transparent to-transparent dark:from-[#f6b56d]/6" />
                <div class="relative flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div class="space-y-2">
                        <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            Menu Sync
                        </span>
                        <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Tablet Categories
                        </h2>
                        <p class="text-sm text-muted-foreground">Drag to reorder · changes reflect on tablets immediately</p>
                    </div>
                    <Button size="sm" @click="showCreate = true">
                        <Plus class="mr-1.5 h-3.5 w-3.5" />
                        New Category
                    </Button>
                </div>
            </section>

            <!-- Two-pane layout -->
            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="flex min-h-[480px] divide-x divide-black/8 dark:divide-white/10">
                    <!-- Left pane: category list -->
                    <div class="w-72 shrink-0 flex flex-col">
                        <div class="border-b border-black/8 px-4 py-3 dark:border-white/10">
                            <p class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                Categories
                                <Badge variant="secondary" class="ml-1 text-[9px]">{{ categories.length }} active</Badge>
                            </p>
                        </div>
                        <div class="flex-1 overflow-y-auto py-1">
                            <div
                                v-for="(cat, index) in [...categories].sort((a, b) => a.sort_order - b.sort_order)"
                                :key="cat.id"
                                class="flex cursor-pointer items-center gap-2 px-3 py-2.5 transition-colors"
                                :class="{
                                    'bg-[#2a1e0c] text-[#F6B56D]': selectedId === cat.id,
                                    'hover:bg-black/4 dark:hover:bg-white/4': selectedId !== cat.id,
                                    'border-t-2 border-[#f6b56d]/60': dragOverId === cat.id,
                                }"
                                draggable="true"
                                @click="selectedId = cat.id"
                                @dragstart="onDragStart(cat.id)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver($event, cat.id)"
                                @drop="onDrop(cat.id)"
                            >
                                <GripVertical class="h-3.5 w-3.5 shrink-0 cursor-grab text-muted-foreground/40" />
                                <span class="w-4 shrink-0 font-mono text-[10px] text-muted-foreground">#{{ index + 1 }}</span>
                                <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ cat.name }}</span>
                                <span class="font-mono text-[10px] text-muted-foreground">{{ cat.menu_count }}</span>
                                <button
                                    class="shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-semibold uppercase transition-colors"
                                    :class="cat.is_active
                                        ? 'bg-woosoo-green/10 text-woosoo-green hover:bg-woosoo-green/20'
                                        : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                    @click.stop="toggleActive(cat)"
                                >
                                    {{ cat.is_active ? 'Active' : 'Off' }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right pane: category detail -->
                    <div class="flex min-w-0 flex-1 flex-col">
                        <div v-if="!selectedCategory" class="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                            Select a category to manage its menus.
                        </div>

                        <template v-else>
                            <!-- Right pane header -->
                            <div class="flex items-center justify-between border-b border-black/8 px-5 py-3 dark:border-white/10">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="selectedCategory.is_active
                                            ? 'bg-woosoo-green/10 text-woosoo-green'
                                            : 'bg-muted text-muted-foreground'"
                                    >
                                        <span class="h-1.5 w-1.5 rounded-full"
                                            :class="selectedCategory.is_active ? 'bg-woosoo-green' : 'bg-muted-foreground/40'"
                                        />
                                        {{ selectedCategory.is_active ? 'Active' : 'Off' }}
                                    </span>
                                    <h3 class="font-semibold text-foreground">{{ selectedCategory.name }}</h3>
                                </div>
                                <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openEdit(selectedCategory)">
                                    <Pencil class="mr-1 h-3 w-3" /> Edit
                                </Button>
                            </div>

                            <!-- Fields strip -->
                            <div class="grid grid-cols-3 gap-4 border-b border-black/8 px-5 py-3 dark:border-white/10">
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Slug</p>
                                    <p class="mt-0.5 font-mono text-xs">{{ selectedCategory.slug }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Sort Order</p>
                                    <p class="mt-0.5 font-mono text-xs">#{{ selectedCategory.sort_order }}</p>
                                </div>
                                <div>
                                    <p class="text-[9px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Menu Items</p>
                                    <p class="mt-0.5 font-mono text-xs">{{ selectedCategory.menu_count }}</p>
                                </div>
                            </div>

                            <!-- Attached menus label -->
                            <div class="flex items-center justify-between px-5 py-3">
                                <p class="text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Attached Menus</p>
                            </div>

                            <!-- Menu list -->
                            <div class="flex-1 overflow-y-auto">
                                <div v-if="selectedCategory.menus.length === 0" class="px-5 py-8 text-center text-sm text-muted-foreground">
                                    No items attached. Use "+ Attach Menu" below.
                                </div>
                                <div v-else>
                                    <div
                                        v-for="menu in selectedCategory.menus"
                                        :key="menu.id"
                                        class="flex items-center gap-3 border-b border-black/5 px-5 py-2.5 last:border-b-0 dark:border-white/5"
                                    >
                                        <span class="min-w-0 flex-1 truncate text-sm text-foreground">{{ menu.name }}</span>
                                        <button
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase transition-colors"
                                            :class="menu.is_featured
                                                ? 'bg-[#f6b56d]/20 text-[#f6b56d] hover:bg-[#f6b56d]/30'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80'"
                                            @click="toggleFeatured(selectedCategory, menu)"
                                        >
                                            <Star class="inline-block h-2.5 w-2.5" :fill="menu.is_featured ? 'currentColor' : 'none'" />
                                            {{ menu.is_featured ? 'Featured' : 'Feature' }}
                                        </button>
                                        <button
                                            class="shrink-0 text-muted-foreground/60 transition-colors hover:text-destructive"
                                            @click="confirmDetach(selectedCategory, menu)"
                                        >
                                            <X class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Footer: attach + delete -->
                            <div class="flex items-center justify-between border-t border-black/8 px-5 py-3 dark:border-white/10">
                                <Button variant="outline" size="sm" @click="showAttach = true">
                                    <Plus class="mr-1.5 h-3.5 w-3.5" />
                                    Attach Menu
                                </Button>
                                <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-destructive hover:text-destructive" @click="deleteTarget = selectedCategory">
                                    <Trash2 class="mr-1 h-3 w-3" />
                                    Delete Category
                                </Button>
                            </div>
                        </template>
                    </div>
                </div>
            </section>
        </div>

        <!-- Create dialog -->
        <Dialog :open="showCreate" @update:open="showCreate = $event">
            <DialogContent>
                <DialogHeader><DialogTitle>New Category</DialogTitle></DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
                    <div class="grid gap-1.5">
                        <Label>Name</Label>
                        <Input v-model="createForm.name" required />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Slug <span class="text-muted-foreground">(optional)</span></Label>
                        <Input v-model="createForm.slug" placeholder="auto-generated from name" />
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
                <DialogHeader><DialogTitle>Edit Category</DialogTitle></DialogHeader>
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
                        <Button type="submit" :disabled="editForm.processing">Save</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Attach menus dialog -->
        <Dialog :open="showAttach" @update:open="(val) => { if (!val) { showAttach = false; selectedAttachIds = []; attachSearch = '' } }">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Attach Menu Items</DialogTitle>
                </DialogHeader>
                <div class="flex flex-col gap-3">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                        <Input v-model="attachSearch" placeholder="Search menus…" class="pl-8" />
                    </div>
                    <div class="max-h-64 overflow-y-auto rounded-md border">
                        <div
                            v-for="m in filteredUnattached"
                            :key="m.id"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2.5 transition-colors hover:bg-muted/50"
                            :class="{ 'bg-[#f6b56d]/10': selectedAttachIds.includes(m.id) }"
                            @click="toggleAttachSelection(m.id)"
                        >
                            <div class="h-3.5 w-3.5 shrink-0 rounded border"
                                :class="selectedAttachIds.includes(m.id) ? 'border-[#f6b56d] bg-[#f6b56d]' : 'border-border'"
                            />
                            <span class="text-sm">{{ m.name }}</span>
                        </div>
                        <p v-if="filteredUnattached.length === 0" class="py-6 text-center text-xs text-muted-foreground">
                            No unattached menus found.
                        </p>
                    </div>
                    <p class="text-xs text-muted-foreground">{{ selectedAttachIds.length }} selected</p>
                </div>
                <DialogFooter>
                    <Button variant="outline" @click="showAttach = false">Cancel</Button>
                    <Button :disabled="selectedAttachIds.length === 0" @click="submitAttach">
                        Attach {{ selectedAttachIds.length > 0 ? `(${selectedAttachIds.length})` : '' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Detach confirmation -->
        <AlertDialog :open="detachTarget !== null" @update:open="(val) => { if (!val) detachTarget = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Detach menu?</AlertDialogTitle>
                    <AlertDialogDescription>
                        "{{ detachTarget?.name }}" will be removed from this category.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="executeDetach">Detach</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>

        <!-- Delete category confirmation -->
        <AlertDialog :open="deleteTarget !== null" @update:open="(val) => { if (!val) deleteTarget = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete category?</AlertDialogTitle>
                    <AlertDialogDescription>
                        "{{ deleteTarget?.name }}" and all its menu attachments will be removed permanently.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive text-destructive-foreground" @click="executeDelete">Delete</AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
