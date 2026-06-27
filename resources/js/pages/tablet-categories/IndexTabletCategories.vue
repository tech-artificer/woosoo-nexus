<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, Pencil, Trash2, GripVertical, Star, X, Search, RefreshCw } from 'lucide-vue-next'
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
    receipt_name?: string | null
    group_id: number
    group_name: string
    category_name: string
    course_name?: string | null
}

interface KryptonMenuGroup {
    key: string
    label: string
    categoryName: string
    items: UnattachedMenu[]
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

const sortedCategories = computed(() =>
    [...props.categories].sort((a, b) => a.sort_order - b.sort_order),
)

// ─── Category Drag Reorder ─────────────────────────────────────────────────
const dragOverId = ref<number | null>(null)
const isReordering = ref(false)
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
    const ordered = [...sortedCategories.value]
    const fromIdx = ordered.findIndex((c) => c.id === draggingId)
    const toIdx   = ordered.findIndex((c) => c.id === targetId)
    if (fromIdx === -1 || toIdx === -1) return
    const [moved] = ordered.splice(fromIdx, 1)
    ordered.splice(toIdx, 0, moved)
    dragOverId.value = null
    isReordering.value = true
    router.put(route('tablet-categories.reorder'), {
        ids: ordered.map((c) => c.id),
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Order saved.'),
        onError: () => toast.error('Failed to save order.'),
        onFinish: () => { isReordering.value = false },
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

const groupedUnattached = computed((): KryptonMenuGroup[] => {
    const needle = attachSearch.value.trim().toLowerCase()
    const filtered = (props.unattachedMenus ?? []).filter(
        (m) =>
            !needle ||
            m.name.toLowerCase().includes(needle) ||
            (m.receipt_name ?? '').toLowerCase().includes(needle) ||
            m.group_name.toLowerCase().includes(needle) ||
            m.category_name.toLowerCase().includes(needle),
    )

    const map = new Map<string, KryptonMenuGroup>()
    for (const menu of filtered) {
        const key = `${menu.category_name}||${menu.group_name}`
        if (!map.has(key)) {
            map.set(key, { key, label: menu.group_name, categoryName: menu.category_name, items: [] })
        }
        map.get(key)!.items.push(menu)
    }
    return Array.from(map.values())
})

function isGroupAllSelected(group: KryptonMenuGroup): boolean {
    return group.items.length > 0 && group.items.every((m) => selectedAttachIds.value.includes(m.id))
}

function isGroupPartialSelected(group: KryptonMenuGroup): boolean {
    return group.items.some((m) => selectedAttachIds.value.includes(m.id)) && !isGroupAllSelected(group)
}

function toggleGroupSelection(group: KryptonMenuGroup): void {
    if (isGroupAllSelected(group)) {
        selectedAttachIds.value = selectedAttachIds.value.filter((id) => !group.items.some((m) => m.id === id))
    } else {
        for (const m of group.items) {
            if (!selectedAttachIds.value.includes(m.id)) {
                selectedAttachIds.value.push(m.id)
            }
        }
    }
}

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
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-woosoo-accent/10 via-transparent to-transparent dark:from-woosoo-accent/6" />
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
                <div class="flex min-h-[480px] flex-col lg:flex-row divide-y lg:divide-y-0 lg:divide-x divide-black/8 dark:divide-white/10">
                    <!-- Left pane: category list -->
                    <div class="w-full lg:w-72 lg:shrink-0 flex flex-col">
                        <div class="border-b border-black/8 px-4 py-3 dark:border-white/10">
                            <p class="flex items-center text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                Categories
                                <Badge variant="secondary" class="ml-1 text-[9px]">{{ categories.filter(c => c.is_active).length }} active</Badge>
                                <RefreshCw v-if="isReordering" class="ml-1.5 h-3 w-3 animate-spin" />
                            </p>
                        </div>
                        <div class="flex-1 overflow-y-auto py-1">
                            <div
                                v-for="(cat, index) in sortedCategories"
                                :key="cat.id"
                                class="flex cursor-pointer items-center gap-2 px-3 py-2.5 transition-colors"
                                :class="{
                                    'bg-woosoo-accent/12 text-woosoo-accent border border-woosoo-accent/30': selectedId === cat.id,
                                    'hover:bg-black/4 dark:hover:bg-white/4': selectedId !== cat.id,
                                    'border-t-2 border-woosoo-accent/60': dragOverId === cat.id,
                                }"
                                draggable="true"
                                @click="selectedId = cat.id"
                                @dragstart="onDragStart(cat.id)"
                                @dragend="onDragEnd"
                                @dragover="onDragOver($event, cat.id)"
                                @drop="onDrop(cat.id)"
                            >
                                <GripVertical class="h-4 w-4 shrink-0 cursor-grab rounded p-0.5 text-muted-foreground/50 hover:bg-black/5 dark:hover:bg-white/5" />
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-black/5 font-mono text-[10px] font-semibold text-muted-foreground dark:bg-white/8">
                                    {{ index + 1 }}
                                </span>
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
                                                ? 'bg-woosoo-accent/20 text-woosoo-accent hover:bg-woosoo-accent/30'
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
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Attach Menu Items — {{ selectedCategory?.name }}</DialogTitle>
                </DialogHeader>
                <div class="flex flex-col gap-3">
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
                        <Input v-model="attachSearch" placeholder="Search by name, code, group, or category…" class="pl-8" />
                    </div>

                    <!-- Grouped menu list -->
                    <div class="max-h-[400px] overflow-y-auto rounded-md border">
                        <template v-if="groupedUnattached.length > 0">
                            <div v-for="group in groupedUnattached" :key="group.key">
                                <!-- Group header with select-all -->
                                <div class="sticky top-0 z-10 flex items-center gap-2 border-b border-border/50 bg-muted/80 px-3 py-1.5 backdrop-blur-sm">
                                    <Checkbox
                                        :id="`grp-${group.key}`"
                                        :model-value="isGroupPartialSelected(group) ? 'indeterminate' : isGroupAllSelected(group)"
                                        @update:model-value="toggleGroupSelection(group)"
                                    />
                                    <Label :for="`grp-${group.key}`" class="flex flex-1 cursor-pointer items-center gap-2">
                                        <span class="text-[10px] font-semibold tracking-[0.18em] text-foreground uppercase">{{ group.label }}</span>
                                        <span class="text-[9px] text-muted-foreground">{{ group.categoryName }}</span>
                                        <span class="ml-auto text-[9px] text-muted-foreground">
                                            {{ group.items.filter(m => selectedAttachIds.includes(m.id)).length }}/{{ group.items.length }}
                                        </span>
                                    </Label>
                                </div>

                                <!-- Items within group -->
                                <div
                                    v-for="m in group.items"
                                    :key="m.id"
                                    class="flex cursor-pointer items-center gap-2 border-b border-border/25 pl-8 pr-3 py-2 last:border-0 hover:bg-muted/40"
                                    :class="{ 'bg-woosoo-accent/6': selectedAttachIds.includes(m.id) }"
                                    @click="toggleAttachSelection(m.id)"
                                >
                                    <Checkbox
                                        :id="`attach-${m.id}`"
                                        :model-value="selectedAttachIds.includes(m.id)"
                                        @update:model-value="(v: boolean | 'indeterminate') => v ? (!selectedAttachIds.includes(m.id) && selectedAttachIds.push(m.id)) : (selectedAttachIds = selectedAttachIds.filter(id => id !== m.id))"
                                        @click.stop
                                    />
                                    <Label :for="`attach-${m.id}`" class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-sm" @click.stop>
                                        <span v-if="m.receipt_name" class="shrink-0 rounded bg-accent/15 px-1.5 py-0.5 font-mono text-[10px] font-medium text-woosoo-accent">
                                            {{ m.receipt_name }}
                                        </span>
                                        <span class="truncate">{{ m.name }}</span>
                                        <span v-if="m.course_name" class="ml-auto shrink-0 text-[9px] text-muted-foreground">{{ m.course_name }}</span>
                                    </Label>
                                </div>
                            </div>
                        </template>
                        <p v-else class="py-8 text-center text-xs text-muted-foreground">
                            {{ attachSearch ? 'No menus matched the search.' : 'No unattached menus available.' }}
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
