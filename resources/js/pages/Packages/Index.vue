<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { ListCheck, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'

interface AllowedMenuVm {
    id?: number
    krypton_menu_id: number
    menu_name: string
    menu_type: string
    extra_price: number
    quantity_limit: number
    is_required: boolean
    is_default: boolean
    is_active: boolean
    sort_order: number
}

interface PackageVm {
    id: number
    name: string
    description?: string | null
    base_price: number
    min_meat: number
    max_meat: number
    min_side: number
    max_side: number
    min_dessert: number
    max_dessert: number
    min_beverage: number
    max_beverage: number
    is_active: boolean
    sort_order: number
    allowed_menus: AllowedMenuVm[]
}

interface MenuOption {
    id: number
    name: string
    receipt_name?: string | null
    is_modifier_only: boolean
}

interface PackagesPageProps {
    title: string
    description: string
    packages: PackageVm[]
    menuOptions: MenuOption[]
}

const MENU_TYPES = ['meat', 'side', 'dessert', 'drinks'] as const
type MenuType = (typeof MENU_TYPES)[number]

const props = defineProps<PackagesPageProps>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Packages', href: route('packages.index') }]

// ── Package create / edit ──────────────────────────────────────────────────

const showCreate = ref(false)
const editingId = ref<number | null>(null)
const pendingDelete = ref<PackageVm | null>(null)

const form = useForm({
    name: '',
    description: '',
    base_price: null as number | null,
    min_meat: 1,
    max_meat: 3,
    min_side: 0,
    max_side: 5,
    min_dessert: 0,
    max_dessert: 2,
    min_beverage: 0,
    max_beverage: 2,
    is_active: true,
    sort_order: 0,
})

const dialogOpen = computed(() => showCreate.value || editingId.value !== null)

const orderedPackages = computed(() =>
    [...(props.packages ?? [])].sort((a, b) => a.sort_order - b.sort_order),
)

const activeCount = computed(() => (props.packages ?? []).filter((p) => p.is_active).length)

function formatPrice(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : parseFloat(String(value))
    if (!Number.isFinite(n)) return '—'
    return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function resetForm(): void {
    showCreate.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
    form.base_price = null
    form.min_meat = 1
    form.max_meat = 3
    form.min_side = 0
    form.max_side = 5
    form.min_dessert = 0
    form.max_dessert = 2
    form.min_beverage = 0
    form.max_beverage = 2
    form.is_active = true
    form.sort_order = 0
}

function openCreate(): void {
    resetForm()
    showCreate.value = true
}

function openEdit(item: PackageVm): void {
    resetForm()
    editingId.value = item.id
    form.name = item.name
    form.description = item.description ?? ''
    form.base_price = item.base_price
    form.min_meat = item.min_meat
    form.max_meat = item.max_meat
    form.min_side = item.min_side
    form.max_side = item.max_side
    form.min_dessert = item.min_dessert
    form.max_dessert = item.max_dessert
    form.min_beverage = item.min_beverage
    form.max_beverage = item.max_beverage
    form.is_active = item.is_active
    form.sort_order = item.sort_order
}

function closeDialog(): void {
    resetForm()
}

function submit(): void {
    if (editingId.value) {
        form.put(route('packages.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Package updated.')
                closeDialog()
            },
            onError: () => toast.error('Failed to update package.'),
        })
        return
    }
    form.post(route('packages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Package created.')
            closeDialog()
        },
        onError: () => toast.error('Failed to create package.'),
    })
}

function confirmDelete(item: PackageVm): void {
    pendingDelete.value = item
}

function executeDelete(): void {
    if (!pendingDelete.value) return
    const item = pendingDelete.value
    pendingDelete.value = null
    router.delete(route('packages.destroy', item.id), {
        preserveScroll: true,
        onSuccess: () => toast.success(`"${item.name}" deleted.`),
        onError: () => toast.error('Failed to delete package.'),
    })
}

// ── Manage menus (sync) ────────────────────────────────────────────────────

interface SyncEntry {
    krypton_menu_id: number
    menu_type: MenuType
    quantity_limit: number
    sort_order: number
}

const managingPackage = ref<PackageVm | null>(null)
const menuSyncSearch = ref('')

const syncForm = useForm({
    allowed_menus: [] as SyncEntry[],
})

const syncDialogOpen = computed(() => managingPackage.value !== null)

const filteredSyncOptions = computed(() => {
    const needle = menuSyncSearch.value.trim().toLowerCase()
    if (!needle) return props.menuOptions ?? []
    return (props.menuOptions ?? []).filter(
        (m) =>
            m.name.toLowerCase().includes(needle) ||
            String(m.id).includes(needle) ||
            (m.receipt_name ?? '').toLowerCase().includes(needle),
    )
})

function isMenuSelected(menuId: number): boolean {
    return syncForm.allowed_menus.some((m) => m.krypton_menu_id === menuId)
}

function getSyncEntry(menuId: number): SyncEntry | undefined {
    return syncForm.allowed_menus.find((m) => m.krypton_menu_id === menuId)
}

function toggleSyncMenu(menu: MenuOption, checked: boolean): void {
    if (checked) {
        if (!isMenuSelected(menu.id)) {
            syncForm.allowed_menus = [
                ...syncForm.allowed_menus,
                {
                    krypton_menu_id: menu.id,
                    menu_type: 'meat',
                    quantity_limit: 1,
                    sort_order: syncForm.allowed_menus.length,
                },
            ]
        }
    } else {
        syncForm.allowed_menus = syncForm.allowed_menus.filter((m) => m.krypton_menu_id !== menu.id)
    }
}

function setSyncEntryType(menuId: number, type: string): void {
    const entry = getSyncEntry(menuId)
    if (entry) {
        entry.menu_type = type as MenuType
    }
}

function setSyncEntryQty(menuId: number, qty: number): void {
    const entry = getSyncEntry(menuId)
    if (entry) {
        entry.quantity_limit = qty
    }
}

function openManageMenus(item: PackageVm): void {
    managingPackage.value = item
    syncForm.allowed_menus = item.allowed_menus.map((am, idx) => ({
        krypton_menu_id: am.krypton_menu_id,
        menu_type: (MENU_TYPES.includes(am.menu_type as MenuType) ? am.menu_type : 'meat') as MenuType,
        quantity_limit: am.quantity_limit,
        sort_order: am.sort_order ?? idx,
    }))
    menuSyncSearch.value = ''
}

function closeManageMenus(): void {
    managingPackage.value = null
    syncForm.reset()
    menuSyncSearch.value = ''
}

function submitSyncMenus(): void {
    if (!managingPackage.value) return
    syncForm.post(route('packages.sync-menus', managingPackage.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Allowed menus saved.')
            closeManageMenus()
        },
        onError: () => toast.error('Failed to save allowed menus.'),
    })
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="space-y-5">
            <!-- Hero header -->
            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-woosoo-accent/10 via-transparent to-transparent dark:from-woosoo-accent/6" />
                <div class="relative flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div class="space-y-2">
                        <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                            Dining packages
                        </span>
                        <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Packages
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ activeCount }} active package{{ activeCount !== 1 ? 's' : '' }}
                        </p>
                    </div>
                    <Button size="sm" @click="openCreate">
                        <Plus class="mr-1.5 h-3.5 w-3.5" />
                        New Package
                    </Button>
                </div>
            </section>

            <!-- Package cards -->
            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="p-4 sm:p-6">
                    <div v-if="orderedPackages.length === 0" class="py-16 text-center text-sm text-muted-foreground">
                        No packages configured yet.
                        <button class="ml-1 underline" @click="openCreate">Add the first one.</button>
                    </div>

                    <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                        <div
                            v-for="item in orderedPackages"
                            :key="item.id"
                            class="relative flex flex-col gap-4 rounded-[18px] border border-black/8 bg-white/72 p-5 shadow-sm transition-all duration-150 hover:border-woosoo-accent/25 dark:border-white/10 dark:bg-white/[0.05]"
                        >
                            <!-- Name + description -->
                            <div>
                                <h3 class="text-lg font-semibold text-foreground">{{ item.name }}</h3>
                                <p v-if="item.description" class="mt-1 text-sm text-muted-foreground">{{ item.description }}</p>
                            </div>

                            <!-- Price -->
                            <div class="flex items-baseline gap-1">
                                <span class="font-mono text-3xl font-semibold text-woosoo-accent">₱{{ formatPrice(item.base_price) }}</span>
                                <span class="text-xs text-muted-foreground">/ person</span>
                            </div>

                            <!-- Category limits -->
                            <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                                <div class="flex items-center justify-between gap-1 rounded-md bg-muted/40 px-2 py-1">
                                    <span class="text-muted-foreground">Meat</span>
                                    <span class="font-medium">{{ item.min_meat }}–{{ item.max_meat }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-1 rounded-md bg-muted/40 px-2 py-1">
                                    <span class="text-muted-foreground">Side</span>
                                    <span class="font-medium">{{ item.min_side }}–{{ item.max_side }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-1 rounded-md bg-muted/40 px-2 py-1">
                                    <span class="text-muted-foreground">Dessert</span>
                                    <span class="font-medium">{{ item.min_dessert }}–{{ item.max_dessert }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-1 rounded-md bg-muted/40 px-2 py-1">
                                    <span class="text-muted-foreground">Beverage</span>
                                    <span class="font-medium">{{ item.min_beverage }}–{{ item.max_beverage }}</span>
                                </div>
                            </div>

                            <!-- Allowed menus count -->
                            <div class="text-xs text-muted-foreground">
                                <span v-if="(item.allowed_menus ?? []).length > 0">
                                    {{ (item.allowed_menus ?? []).length }} allowed menu{{ (item.allowed_menus ?? []).length !== 1 ? 's' : '' }}
                                </span>
                                <span v-else class="italic">No menus linked</span>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between border-t border-black/5 pt-3 dark:border-white/8">
                                <Badge :variant="item.is_active ? 'default' : 'secondary'" class="text-[10px]">
                                    {{ item.is_active ? 'Published' : 'Inactive' }}
                                </Badge>
                                <div class="flex gap-1">
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openManageMenus(item)">
                                        <ListCheck class="mr-1 h-3 w-3" /> Menus
                                    </Button>
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openEdit(item)">
                                        <Pencil class="mr-1 h-3 w-3" /> Edit
                                    </Button>
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-destructive hover:text-destructive" @click="confirmDelete(item)">
                                        <Trash2 class="mr-1 h-3 w-3" /> Delete
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Create / Edit dialog -->
        <Dialog :open="dialogOpen" @update:open="(val) => { if (!val) closeDialog() }">
            <DialogContent class="max-h-[90vh] max-w-lg overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ editingId ? 'Edit Package' : 'New Package' }}</DialogTitle>
                </DialogHeader>
                <form class="flex flex-col gap-4" @submit.prevent="submit">
                    <div class="grid gap-1.5">
                        <Label for="pkg_name">Name</Label>
                        <Input id="pkg_name" v-model="form.name" placeholder="e.g. Classic Feast" required />
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="pkg_description">Description</Label>
                        <Textarea id="pkg_description" v-model="form.description" rows="3" placeholder="Guest-facing description" />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="pkg_price">Base Price (₱)</Label>
                        <Input id="pkg_price" v-model.number="form.base_price" type="number" min="0" step="0.01" placeholder="449.00" />
                        <p v-if="form.errors.base_price" class="text-xs text-destructive">{{ form.errors.base_price }}</p>
                    </div>

                    <!-- Category limits grid -->
                    <fieldset class="rounded-md border border-border/60 p-3">
                        <legend class="px-1 text-xs font-semibold text-muted-foreground">Category limits (min – max)</legend>
                        <div class="mt-2 grid grid-cols-2 gap-x-4 gap-y-3">
                            <div class="grid gap-1">
                                <Label class="text-xs">Meat</Label>
                                <div class="flex items-center gap-1">
                                    <Input v-model.number="form.min_meat" type="number" min="0" class="h-8 text-xs" />
                                    <span class="text-muted-foreground">–</span>
                                    <Input v-model.number="form.max_meat" type="number" min="0" class="h-8 text-xs" />
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Side</Label>
                                <div class="flex items-center gap-1">
                                    <Input v-model.number="form.min_side" type="number" min="0" class="h-8 text-xs" />
                                    <span class="text-muted-foreground">–</span>
                                    <Input v-model.number="form.max_side" type="number" min="0" class="h-8 text-xs" />
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Dessert</Label>
                                <div class="flex items-center gap-1">
                                    <Input v-model.number="form.min_dessert" type="number" min="0" class="h-8 text-xs" />
                                    <span class="text-muted-foreground">–</span>
                                    <Input v-model.number="form.max_dessert" type="number" min="0" class="h-8 text-xs" />
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Beverage</Label>
                                <div class="flex items-center gap-1">
                                    <Input v-model.number="form.min_beverage" type="number" min="0" class="h-8 text-xs" />
                                    <span class="text-muted-foreground">–</span>
                                    <Input v-model.number="form.max_beverage" type="number" min="0" class="h-8 text-xs" />
                                </div>
                            </div>
                        </div>
                    </fieldset>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-1.5">
                            <Label for="pkg_sort">Display Order</Label>
                            <Input id="pkg_sort" v-model.number="form.sort_order" type="number" min="0" />
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Switch
                            id="pkg_active"
                            :model-value="form.is_active"
                            @update:model-value="(v) => (form.is_active = Boolean(v))"
                        />
                        <Label for="pkg_active">Published — visible to ordering devices</Label>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" type="button" @click="closeDialog">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : editingId ? 'Update Package' : 'Create Package' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Manage Menus dialog -->
        <Dialog :open="syncDialogOpen" @update:open="(val) => { if (!val) closeManageMenus() }">
            <DialogContent class="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Manage Menus — {{ managingPackage?.name }}</DialogTitle>
                </DialogHeader>

                <div class="flex flex-col gap-4">
                    <Input v-model="menuSyncSearch" placeholder="Search by name, receipt code, or ID" />

                    <!-- Selected menus with type + qty -->
                    <div v-if="syncForm.allowed_menus.length > 0" class="space-y-2">
                        <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                            Selected ({{ syncForm.allowed_menus.length }})
                        </p>
                        <div
                            v-for="entry in syncForm.allowed_menus"
                            :key="entry.krypton_menu_id"
                            class="flex items-center gap-2 rounded-md border border-border/60 bg-muted/20 px-3 py-2"
                        >
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">
                                {{ (props.menuOptions ?? []).find((m) => m.id === entry.krypton_menu_id)?.name ?? `#${entry.krypton_menu_id}` }}
                            </span>
                            <Select :model-value="entry.menu_type" @update:model-value="(v) => setSyncEntryType(entry.krypton_menu_id, v)">
                                <SelectTrigger class="h-7 w-24 text-xs">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="t in MENU_TYPES" :key="t" :value="t">{{ t }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                type="number"
                                min="1"
                                :model-value="entry.quantity_limit"
                                class="h-7 w-14 text-xs"
                                @input="(e) => setSyncEntryQty(entry.krypton_menu_id, Number((e.target as HTMLInputElement).value))"
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-destructive"
                                @click="toggleSyncMenu({ id: entry.krypton_menu_id, name: '', is_modifier_only: false }, false)"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <!-- Available menus checklist -->
                    <div class="max-h-56 overflow-y-auto rounded-md border">
                        <div
                            v-for="menu in filteredSyncOptions"
                            :key="menu.id"
                            class="flex items-center gap-2 border-b border-border/40 px-3 py-2 last:border-0 hover:bg-muted/40"
                        >
                            <Checkbox
                                :id="`sync-${menu.id}`"
                                :model-value="isMenuSelected(menu.id)"
                                @update:model-value="(v) => toggleSyncMenu(menu, v === true)"
                            />
                            <Label :for="`sync-${menu.id}`" class="flex-1 cursor-pointer text-sm">
                                {{ menu.name }}
                                <span class="ml-1 text-xs text-muted-foreground">#{{ menu.id }}</span>
                            </Label>
                        </div>
                        <p v-if="filteredSyncOptions.length === 0" class="py-6 text-center text-xs text-muted-foreground">
                            No menu items matched.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="closeManageMenus">Cancel</Button>
                    <Button :disabled="syncForm.processing" @click="submitSyncMenus">
                        {{ syncForm.processing ? 'Saving…' : 'Save Menus' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <AlertDialog :open="!!pendingDelete" @update:open="(v) => { if (!v) pendingDelete = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete package?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently remove <strong>{{ pendingDelete?.name }}</strong> and its allowed menus.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel @click="pendingDelete = null">Cancel</AlertDialogCancel>
                    <AlertDialogAction class="bg-destructive hover:bg-destructive/90" @click="executeDelete">
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
