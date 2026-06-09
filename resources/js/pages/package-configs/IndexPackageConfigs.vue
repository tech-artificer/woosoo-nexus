<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, Pencil, Trash2, Check } from 'lucide-vue-next'
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

interface AllowedMenu {
    id: number
    krypton_menu_id: number
    name: string
    menu_type: string | null
    sort_order: number
    is_active: boolean
}

interface PackageConfigVm {
    id: number
    name: string
    description: string | null
    base_price: string
    is_active: boolean
    sort_order: number
    menus: AllowedMenu[]
}

const props = defineProps<{ packages: PackageConfigVm[] }>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Packages', href: route('package-configs.index') },
]

const orderedPackages = computed(() =>
    [...props.packages].sort((a, b) => a.sort_order - b.sort_order),
)

const activeCount = computed(() => props.packages.filter((p) => p.is_active).length)
const allPublished = computed(() => props.packages.length > 0 && props.packages.every((p) => p.is_active))

function tierLabel(index: number): string {
    return ['ENTRY TIER', 'MID TIER', 'PREMIUM TIER'][index] ?? `TIER ${index + 1}`
}

function menusByType(pkg: PackageConfigVm, type: string) {
    return pkg.menus.filter((m) => (m.menu_type ?? 'meat') === type && m.is_active)
}

function isBestSeller(index: number): boolean {
    return index === Math.floor(orderedPackages.value.length / 2)
}

// ─── Create ────────────────────────────────────────────────────────────────
const showCreate = ref(false)
const createForm = useForm({ name: '', description: '', base_price: '', is_active: true, sort_order: 0 })

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
const editForm = useForm({ name: '', description: '', base_price: '', is_active: true, sort_order: 0 })

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
    <Head title="Packages" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-5">
            <!-- Hero header -->
            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#f6b56d]/10 via-transparent to-transparent dark:from-[#f6b56d]/6" />
                <div class="relative flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div class="space-y-2">
                        <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            Dining Tiers
                        </span>
                        <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Packages
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ activeCount }} active package{{ activeCount !== 1 ? 's' : '' }} ·
                            {{ allPublished ? 'All published' : 'Some inactive' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <a :href="route('packages.index')">Krypton Modifiers</a>
                        </Button>
                        <Button size="sm" @click="showCreate = true">
                            <Plus class="mr-1.5 h-3.5 w-3.5" />
                            New Package
                        </Button>
                    </div>
                </div>
            </section>

            <!-- Tier cards grid -->
            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="p-4 sm:p-6">
                    <div v-if="orderedPackages.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                        No packages configured yet.
                        <button class="ml-1 underline" @click="showCreate = true">Add the first one.</button>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="(pkg, index) in orderedPackages"
                            :key="pkg.id"
                            class="relative flex flex-col gap-4 rounded-[18px] border bg-white/60 p-5 transition-all duration-150 dark:bg-white/[0.04]"
                            :class="isBestSeller(index)
                                ? 'border-[#f6b56d]/60 shadow-md shadow-[#f6b56d]/10 dark:border-[#f6b56d]/40'
                                : 'border-black/8 dark:border-white/10'"
                        >
                            <!-- Best seller ribbon -->
                            <div v-if="isBestSeller(index)" class="absolute right-0 top-0 overflow-hidden rounded-tr-[18px]">
                                <div class="translate-x-2 -translate-y-px rotate-45 origin-bottom-left bg-[#f6b56d] px-6 py-0.5 text-[9px] font-bold tracking-widest text-black uppercase">
                                    BEST SELLER
                                </div>
                            </div>

                            <!-- Tier label + name -->
                            <div>
                                <p class="text-[10px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                    {{ tierLabel(index) }}
                                </p>
                                <h3 class="mt-1 text-lg font-semibold text-foreground">{{ pkg.name }}</h3>
                                <p v-if="pkg.description" class="mt-1 text-sm text-muted-foreground">{{ pkg.description }}</p>
                            </div>

                            <!-- Price -->
                            <div class="flex items-baseline gap-1">
                                <span class="font-mono text-3xl font-bold text-[#f6b56d]">₱{{ pkg.base_price }}</span>
                                <span class="text-sm text-muted-foreground">/ pax</span>
                            </div>

                            <!-- Meats section -->
                            <div v-if="menusByType(pkg, 'meat').length > 0">
                                <p class="mb-2 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Meats · {{ menusByType(pkg, 'meat').length }} cuts
                                </p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="m in menusByType(pkg, 'meat')"
                                        :key="m.id"
                                        class="flex items-center gap-2 text-sm text-foreground"
                                    >
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#f6b56d]" />
                                        {{ m.name }}
                                    </li>
                                </ul>
                            </div>

                            <!-- Add-ons/sides section -->
                            <div v-if="menusByType(pkg, 'side').length > 0 || menusByType(pkg, 'dessert').length > 0">
                                <p class="mb-2 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">Add-ons Included</p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="m in [...menusByType(pkg, 'side'), ...menusByType(pkg, 'dessert')]"
                                        :key="m.id"
                                        class="flex items-center gap-2 text-sm text-foreground"
                                    >
                                        <Check class="h-3.5 w-3.5 shrink-0 text-woosoo-green" />
                                        {{ m.name }}
                                    </li>
                                </ul>
                            </div>

                            <!-- All items fallback (if no menu_type separation) -->
                            <div v-if="menusByType(pkg, 'meat').length === 0 && pkg.menus.length > 0">
                                <p class="mb-2 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Included · {{ pkg.menus.length }} items
                                </p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="m in pkg.menus.slice(0, 8)"
                                        :key="m.id"
                                        class="flex items-center gap-2 text-sm text-foreground"
                                    >
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[#f6b56d]" />
                                        {{ m.name }}
                                    </li>
                                    <li v-if="pkg.menus.length > 8" class="text-xs text-muted-foreground">
                                        + {{ pkg.menus.length - 8 }} more
                                    </li>
                                </ul>
                            </div>

                            <!-- Empty menus state -->
                            <div v-if="pkg.menus.length === 0" class="rounded-lg border border-dashed border-border/60 py-4 text-center text-xs text-muted-foreground">
                                No menu items configured
                            </div>

                            <!-- Status badge -->
                            <div class="flex items-center justify-between border-t border-black/5 pt-3 dark:border-white/8">
                                <Badge :variant="pkg.is_active ? 'default' : 'secondary'" class="text-[10px]">
                                    {{ pkg.is_active ? 'Published' : 'Inactive' }}
                                </Badge>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openEdit(pkg)">
                                        <Pencil class="mr-1 h-3 w-3" /> Edit
                                    </Button>
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-destructive hover:text-destructive" @click="deleteTarget = pkg">
                                        <Trash2 class="mr-1 h-3 w-3" /> Delete
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Create dialog -->
        <Dialog :open="showCreate" @update:open="showCreate = $event">
            <DialogContent>
                <DialogHeader><DialogTitle>New Package</DialogTitle></DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submitCreate">
                    <div class="grid gap-1.5">
                        <Label>Name</Label>
                        <Input v-model="createForm.name" required />
                        <p v-if="createForm.errors.name" class="text-xs text-destructive">{{ createForm.errors.name }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Description</Label>
                        <Input v-model="createForm.description" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Price (₱)</Label>
                        <Input v-model="createForm.base_price" type="number" step="0.01" min="0" required />
                        <p v-if="createForm.errors.base_price" class="text-xs text-destructive">{{ createForm.errors.base_price }}</p>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Sort Order</Label>
                        <Input v-model.number="createForm.sort_order" type="number" min="0" />
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch v-model:checked="createForm.is_active" />
                        <Label>Active / Published</Label>
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
                        <Label>Active / Published</Label>
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
