<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Beef, Pencil, Plus, Star, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
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

interface PosMenuSnapshot {
    krypton_menu_id: number
    name: string
    receipt_name?: string | null
    price: number
    is_discountable?: boolean
    is_taxable?: boolean
}

interface AllowedMenuVm {
    id?: number
    krypton_menu_id: number
    menu_name: string
    receipt_name?: string | null
    extra_price?: number
    quantity_limit: number
    is_required?: boolean
    is_default?: boolean
    is_active?: boolean
    sort_order: number
}

interface PackageVm {
    id: number
    krypton_menu_id?: number | null
    name: string
    description?: string | null
    base_price: number | null
    pos_menu?: PosMenuSnapshot | null
    min_meat: number
    max_meat: number
    is_active: boolean
    is_most_popular: boolean
    sort_order: number
    allowed_menus: AllowedMenuVm[]
}

interface PackagesPageProps {
    title: string
    description: string
    packages: PackageVm[]
    packageOptions: PosMenuSnapshot[]
    meatOptions: PosMenuSnapshot[]
}

const props = defineProps<PackagesPageProps>()

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Packages', href: route('packages.index') }]

// ── Package create / edit ──────────────────────────────────────────────────

const showCreate = ref(false)
const editingId = ref<number | null>(null)
const pendingDelete = ref<PackageVm | null>(null)

const form = useForm({
    krypton_menu_id: null as number | null,
    description: '',
    min_meat: 1,
    max_meat: 5,
    is_active: true,
    is_most_popular: false,
    sort_order: 0,
})

const packageAnchorSearch = ref('')

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

function meatList(item: PackageVm): AllowedMenuVm[] {
    return [...(item.allowed_menus ?? [])].sort((a, b) => a.sort_order - b.sort_order)
}

const selectedPackageAnchor = computed(() => {
    if (!form.krypton_menu_id) return null
    return (props.packageOptions ?? []).find((m) => m.krypton_menu_id === form.krypton_menu_id) ?? null
})

const filteredPackageOptions = computed(() => {
    const needle = packageAnchorSearch.value.trim().toLowerCase()
    const options = props.packageOptions ?? []
    if (!needle) return options
    return options.filter(
        (m) =>
            m.name.toLowerCase().includes(needle) ||
            String(m.krypton_menu_id).includes(needle) ||
            (m.receipt_name ?? '').toLowerCase().includes(needle),
    )
})

function resetForm(): void {
    showCreate.value = false
    editingId.value = null
    packageAnchorSearch.value = ''
    form.reset()
    form.clearErrors()
    form.krypton_menu_id = null
    form.min_meat = 1
    form.max_meat = 5
    form.is_active = true
    form.is_most_popular = false
    form.sort_order = 0
}

function openCreate(): void {
    resetForm()
    showCreate.value = true
}

function openEdit(item: PackageVm): void {
    resetForm()
    editingId.value = item.id
    form.krypton_menu_id = item.krypton_menu_id ?? null
    form.description = item.description ?? ''
    form.min_meat = item.min_meat
    form.max_meat = item.max_meat
    form.is_active = item.is_active
    form.is_most_popular = item.is_most_popular
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

function setMostPopular(item: PackageVm): void {
    if (item.is_most_popular) return
    router.post(route('packages.most-popular', item.id), {}, {
        preserveScroll: true,
        onSuccess: () => toast.success(`"${item.name}" is now the most popular package.`),
        onError: () => toast.error('Failed to update most popular package.'),
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

// ── Manage meats (sync) ─────────────────────────────────────────────────────

interface SyncEntry {
    krypton_menu_id: number
    menu_name: string
    extra_price: number
    quantity_limit: number
    is_required: boolean
    is_default: boolean
    is_active: boolean
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
    const options = props.meatOptions ?? []
    if (!needle) return options
    return options.filter(
        (m) =>
            m.name.toLowerCase().includes(needle) ||
            String(m.krypton_menu_id).includes(needle) ||
            (m.receipt_name ?? '').toLowerCase().includes(needle),
    )
})

function isMenuSelected(menuId: number): boolean {
    return syncForm.allowed_menus.some((m) => m.krypton_menu_id === menuId)
}

function toggleSyncMenu(menuId: number, checked: boolean): void {
    if (checked) {
        if (!isMenuSelected(menuId)) {
            const match = (props.meatOptions ?? []).find((m) => m.krypton_menu_id === menuId)
            const menuName = match ? (match.receipt_name ? `${match.name} (${match.receipt_name})` : match.name) : `Menu #${menuId}`

            syncForm.allowed_menus = [
                ...syncForm.allowed_menus,
                {
                    krypton_menu_id: menuId,
                    menu_name: menuName,
                    extra_price: 0,
                    quantity_limit: 1,
                    is_required: false,
                    is_default: false,
                    is_active: true,
                    sort_order: syncForm.allowed_menus.length,
                },
            ]
        }
    } else {
        syncForm.allowed_menus = syncForm.allowed_menus.filter((m) => m.krypton_menu_id !== menuId)
    }
}

function openManageMenus(item: PackageVm): void {
    managingPackage.value = item
    syncForm.allowed_menus = (item.allowed_menus ?? []).map((am, idx) => ({
        krypton_menu_id: am.krypton_menu_id,
        menu_name: am.menu_name,
        extra_price: am.extra_price ?? 0,
        quantity_limit: am.quantity_limit,
        is_required: am.is_required ?? false,
        is_default: am.is_default ?? false,
        is_active: am.is_active ?? true,
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
            toast.success('Meats saved.')
            closeManageMenus()
        },
        onError: () => toast.error('Failed to save meats.'),
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

            <!-- Scope note: non-meat menus are global -->
            <div class="flex items-start gap-2 rounded-2xl border border-woosoo-accent/20 bg-woosoo-accent/5 px-4 py-3 text-xs text-muted-foreground">
                <Beef class="mt-0.5 h-4 w-4 shrink-0 text-woosoo-accent" />
                <p>
                    Packages configure <span class="font-medium text-foreground">meats only</span>.
                    Banchan, sides, desserts &amp; drinks are shared across every package and managed in
                    <Link :href="route('tablet-categories.index')" class="font-medium text-foreground underline underline-offset-2">Tablet Categories</Link>.
                </p>
            </div>

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
                            class="relative flex flex-col gap-4 rounded-[18px] border p-5 shadow-sm transition-all duration-150 dark:bg-white/[0.05]"
                            :class="item.is_most_popular
                                ? 'border-woosoo-accent/60 bg-white/80 ring-1 ring-woosoo-accent/30 dark:border-woosoo-accent/40'
                                : 'border-black/8 bg-white/72 hover:border-woosoo-accent/25 dark:border-white/10'"
                        >
                            <!-- Most popular badge -->
                            <span
                                v-if="item.is_most_popular"
                                class="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-woosoo-accent/15 px-2.5 py-1 text-[10px] font-semibold text-woosoo-accent"
                            >
                                <Star class="h-3 w-3 fill-current" /> Most popular
                            </span>

                            <!-- Name + description -->
                            <div :class="item.is_most_popular ? 'pr-24' : ''">
                                <h3 class="text-lg font-semibold text-foreground">{{ item.name }}</h3>
                                <p v-if="item.description" class="mt-1 text-sm text-muted-foreground">{{ item.description }}</p>
                            </div>

                            <!-- Price -->
                            <div class="flex items-baseline gap-1">
                                <span class="font-mono text-3xl font-semibold text-woosoo-accent">₱{{ formatPrice(item.base_price) }}</span>
                                <span class="text-xs text-muted-foreground">/ person</span>
                            </div>

                            <!-- Meats -->
                            <div>
                                <div class="mb-1.5 flex items-center justify-between">
                                    <span class="text-[11px] font-semibold tracking-[0.16em] text-muted-foreground uppercase">Meats</span>
                                    <span class="text-[11px] text-muted-foreground">pick {{ item.min_meat }}–{{ item.max_meat }}</span>
                                </div>
                                <ul v-if="meatList(item).length > 0" class="space-y-1">
                                    <li
                                        v-for="m in meatList(item).slice(0, 4)"
                                        :key="m.krypton_menu_id"
                                        class="flex items-center gap-2 text-sm text-foreground"
                                    >
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-woosoo-accent" />
                                        {{ m.menu_name }}
                                    </li>
                                    <li v-if="meatList(item).length > 4" class="text-xs text-muted-foreground">
                                        + {{ meatList(item).length - 4 }} more
                                    </li>
                                </ul>
                                <p v-else class="rounded-lg border border-dashed border-border/60 py-3 text-center text-xs text-muted-foreground">
                                    No meats linked
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center justify-between border-t border-black/5 pt-3 dark:border-white/8">
                                <Badge :variant="item.is_active ? 'default' : 'secondary'" class="text-[10px]">
                                    {{ item.is_active ? 'Published' : 'Inactive' }}
                                </Badge>
                                <div class="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="h-7 px-2 text-xs"
                                        :class="item.is_most_popular ? 'text-woosoo-accent' : 'text-muted-foreground'"
                                        :title="item.is_most_popular ? 'Most popular' : 'Set as most popular'"
                                        @click="setMostPopular(item)"
                                    >
                                        <Star class="h-3.5 w-3.5" :class="item.is_most_popular ? 'fill-current' : ''" />
                                    </Button>
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openManageMenus(item)">
                                        <Beef class="mr-1 h-3 w-3" /> Meats
                                    </Button>
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click="openEdit(item)">
                                        <Pencil class="mr-1 h-3 w-3" /> Edit
                                    </Button>
                                    <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-destructive hover:text-destructive" @click="confirmDelete(item)">
                                        <Trash2 class="h-3 w-3" />
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
                        <Label for="pkg_pos_menu">POS menu (order storage id)</Label>
                        <Input
                            v-model="packageAnchorSearch"
                            placeholder="Search POS menus by name, receipt code, or ID"
                            class="mb-2"
                        />
                        <div class="max-h-40 overflow-y-auto rounded-md border">
                            <button
                                v-for="menu in filteredPackageOptions"
                                :key="menu.krypton_menu_id"
                                type="button"
                                class="flex w-full items-center justify-between gap-2 border-b border-border/40 px-3 py-2.5 text-left text-sm last:border-0 hover:bg-muted/40"
                                :class="form.krypton_menu_id === menu.krypton_menu_id ? 'bg-woosoo-accent/10' : ''"
                                @click="form.krypton_menu_id = menu.krypton_menu_id"
                            >
                                <span class="min-w-0 truncate font-medium">{{ menu.name }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground">
                                    #{{ menu.krypton_menu_id }}
                                    <span v-if="menu.receipt_name"> · {{ menu.receipt_name }}</span>
                                </span>
                            </button>
                            <p v-if="filteredPackageOptions.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                                No POS package menus matched.
                            </p>
                        </div>
                        <p v-if="form.errors.krypton_menu_id" class="text-xs text-destructive">{{ form.errors.krypton_menu_id }}</p>
                    </div>

                    <div
                        v-if="selectedPackageAnchor"
                        class="rounded-md border border-border/60 bg-muted/20 px-3 py-2 text-sm"
                    >
                        <p class="font-medium text-foreground">{{ selectedPackageAnchor.name }}</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            ₱{{ formatPrice(selectedPackageAnchor.price) }} / guest
                            <span v-if="selectedPackageAnchor.is_discountable"> · discountable</span>
                            <span v-if="selectedPackageAnchor.is_taxable"> · taxable</span>
                        </p>
                        <p class="mt-1 text-[11px] text-muted-foreground">Price and name sync from POS on save.</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="pkg_description">Description</Label>
                        <Textarea id="pkg_description" v-model="form.description" rows="3" placeholder="Guest-facing description" />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="pkg_sort">Display Order</Label>
                        <Input id="pkg_sort" v-model.number="form.sort_order" type="number" min="0" />
                    </div>

                    <fieldset class="rounded-md border border-border/60 p-3">
                        <legend class="px-1 text-xs font-semibold text-muted-foreground">Meats per guest (min – max)</legend>
                        <div class="mt-2 flex items-center gap-2">
                            <Input v-model.number="form.min_meat" type="number" min="1" max="5" class="h-8 text-xs" />
                            <span class="text-muted-foreground">–</span>
                            <Input v-model.number="form.max_meat" type="number" min="1" max="5" class="h-8 text-xs" />
                        </div>
                        <p v-if="form.errors.min_meat || form.errors.max_meat" class="mt-1 text-xs text-destructive">
                            {{ form.errors.min_meat || form.errors.max_meat }}
                        </p>
                        <p class="mt-2 text-[11px] text-muted-foreground">
                            Sides, desserts &amp; drinks are shared across packages — manage them in Tablet Categories.
                        </p>
                    </fieldset>

                    <div class="flex items-center gap-3">
                        <Switch
                            id="pkg_active"
                            :model-value="form.is_active"
                            @update:model-value="(v) => (form.is_active = Boolean(v))"
                        />
                        <Label for="pkg_active">Published — visible to ordering devices</Label>
                    </div>

                    <div class="flex items-center gap-3">
                        <Switch
                            id="pkg_popular"
                            :model-value="form.is_most_popular"
                            @update:model-value="(v) => (form.is_most_popular = Boolean(v))"
                        />
                        <Label for="pkg_popular">Most popular — highlighted on the tablet (only one package)</Label>
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

        <!-- Manage Meats dialog -->
        <Dialog :open="syncDialogOpen" @update:open="(val) => { if (!val) closeManageMenus() }">
            <DialogContent class="max-h-[90vh] max-w-xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Manage Meats — {{ managingPackage?.name }}</DialogTitle>
                </DialogHeader>

                <div class="flex flex-col gap-4">
                    <p class="text-xs text-muted-foreground">Meat modifiers only (P/B/C codes). Guest pick count is set on the package.</p>
                    <Input v-model="menuSyncSearch" placeholder="Search by name, receipt code, or ID" />

                    <div v-if="syncForm.allowed_menus.length > 0" class="space-y-2">
                        <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            Selected ({{ syncForm.allowed_menus.length }})
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <div
                                v-for="entry in syncForm.allowed_menus"
                                :key="entry.krypton_menu_id"
                                class="inline-flex max-w-full items-center gap-1 rounded-full border border-border/60 bg-muted/30 py-1 pl-3 pr-1"
                            >
                                <span class="min-w-0 truncate text-sm font-medium">
                                    {{ entry.menu_name }}
                                </span>
                                <button
                                    type="button"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                    :aria-label="`Remove ${entry.menu_name}`"
                                    @click="toggleSyncMenu(entry.krypton_menu_id, false)"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="max-h-56 overflow-y-auto rounded-md border">
                        <div
                            v-for="menu in filteredSyncOptions"
                            :key="menu.krypton_menu_id"
                            class="flex items-center gap-2 border-b border-border/40 px-3 py-2 last:border-0 hover:bg-muted/40"
                        >
                            <Checkbox
                                :id="`sync-${menu.krypton_menu_id}`"
                                :model-value="isMenuSelected(menu.krypton_menu_id)"
                                @update:model-value="(v) => toggleSyncMenu(menu.krypton_menu_id, v === true)"
                            />
                            <Label :for="`sync-${menu.krypton_menu_id}`" class="flex-1 cursor-pointer text-sm">
                                {{ menu.name }}
                                <span v-if="menu.receipt_name" class="ml-1 font-mono text-xs text-woosoo-accent">{{ menu.receipt_name }}</span>
                                <span class="ml-1 text-xs text-muted-foreground">#{{ menu.krypton_menu_id }}</span>
                            </Label>
                        </div>
                        <p v-if="filteredSyncOptions.length === 0" class="py-6 text-center text-xs text-muted-foreground">
                            No meat modifiers matched.
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" type="button" @click="closeManageMenus">Cancel</Button>
                    <Button :disabled="syncForm.processing" @click="submitSyncMenus">
                        {{ syncForm.processing ? 'Saving…' : 'Save Meats' }}
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
                        This will permanently remove <strong>{{ pendingDelete?.name }}</strong> and its meats.
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
