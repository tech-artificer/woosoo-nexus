<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Plus, Pencil, Trash2 } from 'lucide-vue-next'
import AppLayout from '@/layouts/AppLayout.vue'
import type { BreadcrumbItem } from '@/types'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Select, SelectContent, SelectItem, SelectLabel, SelectTrigger, SelectValue } from '@/components/ui/select'
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

interface PackageModifierVm {
    id?: number
    krypton_menu_id: number
    sort_order: number
}

interface PackageVm {
    id: number
    name: string
    description?: string | null
    krypton_menu_id: number
    is_active: boolean
    sort_order: number
    modifiers: PackageModifierVm[]
}

interface MenuOption {
    id: number
    name: string
    receipt_name?: string | null
    is_modifier_only: boolean
    price?: number | null
}

interface PackagesPageProps {
    title: string
    description: string
    packages: PackageVm[]
    menuOptions: MenuOption[]
    modifierDescriptions: Record<number, string>
}

const props = defineProps<PackagesPageProps>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Packages', href: route('packages.index') },
]

const showCreate = ref(false)
const editingId = ref<number | null>(null)
const pendingDelete = ref<PackageVm | null>(null)
const modifierSearch = ref('')

const form = useForm({
    name: '',
    description: '',
    krypton_menu_id: 0,
    is_active: true,
    sort_order: 0,
    modifier_ids: [] as number[],
    modifier_descriptions: {} as Record<number, string>,
})

const dialogOpen = computed(() => showCreate.value || editingId.value !== null)

const orderedPackages = computed(() =>
    [...(props.packages ?? [])].sort((a, b) => a.sort_order - b.sort_order),
)

const activeCount = computed(() => (props.packages ?? []).filter((p) => p.is_active).length)

const allPublished = computed(() =>
    (props.packages ?? []).length > 0 && (props.packages ?? []).every((p) => p.is_active),
)

function menuForId(menuId: number): MenuOption | undefined {
    return (props.menuOptions ?? []).find((m) => m.id === menuId)
}

function menuNameForId(menuId: number): string {
    return menuForId(menuId)?.name ?? `#${menuId}`
}

function menuPriceForId(menuId: number): number | null {
    const price = menuForId(menuId)?.price
    if (price === null || price === undefined) return null
    const n = Number(price)
    return Number.isFinite(n) ? n : null
}

function packageDisplayName(item: PackageVm): string {
    const posName = menuNameForId(item.krypton_menu_id)
    return posName.startsWith('#') ? item.name : posName
}

function formatPrice(value: number | string | null | undefined): string {
    const n = typeof value === 'number' ? value : parseFloat(String(value))
    if (!Number.isFinite(n)) return '—'
    return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function menuSelectLabel(menu: MenuOption): string {
    const price = menu.price
    if (price !== null && price !== undefined && Number.isFinite(Number(price))) {
        return `${menu.name} — ₱${formatPrice(price)}`
    }
    return menu.name
}

function modifierLabelList(modifiers: PackageModifierVm[]): string[] {
    const byId = new Map((props.menuOptions ?? []).map((m) => [m.id, m.name]))
    return [...modifiers]
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((m) => byId.get(m.krypton_menu_id) ?? `#${m.krypton_menu_id}`)
}

const modifierLabelsByPackageId = computed(() => {
    const labels = new Map<number, string[]>()
    for (const item of orderedPackages.value) {
        labels.set(item.id, modifierLabelList(item.modifiers ?? []))
    }
    return labels
})

const filteredModifierOptions = computed(() => {
    const needle = modifierSearch.value.trim().toLowerCase()
    if (!needle) return props.menuOptions ?? []
    return (props.menuOptions ?? []).filter((item) =>
        item.name.toLowerCase().includes(needle)
        || String(item.id).includes(needle)
        || (item.receipt_name ?? '').toLowerCase().includes(needle),
    )
})

const selectedModifiers = computed(() => {
    const byId = new Map((props.menuOptions ?? []).map((item) => [item.id, item]))
    return form.modifier_ids.map(
        (id) => byId.get(id) ?? { id, name: `Menu #${id}`, receipt_name: null, is_modifier_only: false } as MenuOption,
    )
})

watch(
    () => form.krypton_menu_id,
    (menuId) => {
        if (!menuId) return
        const menu = menuForId(Number(menuId))
        if (menu?.name) form.name = menu.name
    },
)

function isModifierSelected(menuId: number): boolean {
    return form.modifier_ids.includes(menuId)
}

function toggleModifier(menuId: number, checked: boolean): void {
    if (checked && !form.modifier_ids.includes(menuId)) {
        form.modifier_ids = [...form.modifier_ids, menuId]
        return
    }
    if (!checked) {
        form.modifier_ids = form.modifier_ids.filter((id) => id !== menuId)
    }
}

function resetForm() {
    showCreate.value = false
    editingId.value = null
    form.reset()
    form.clearErrors()
    form.description = ''
    form.is_active = true
    form.krypton_menu_id = 0
    form.sort_order = 0
    form.modifier_ids = []
    form.modifier_descriptions = {}
    modifierSearch.value = ''
}

function openCreate() {
    resetForm()
    showCreate.value = true
}

function openEdit(item: PackageVm) {
    editingId.value = item.id
    form.name = item.name
    form.description = item.description ?? ''
    form.krypton_menu_id = item.krypton_menu_id
    form.is_active = item.is_active
    form.sort_order = item.sort_order
    form.modifier_ids = [...(item.modifiers ?? [])]
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((modifier) => modifier.krypton_menu_id)
    const descriptions: Record<number, string> = {}
    for (const menuId of form.modifier_ids) {
        descriptions[menuId] = props.modifierDescriptions?.[menuId] ?? ''
    }
    form.modifier_descriptions = descriptions
}

function closeDialog() {
    resetForm()
}

function submit() {
    const orderedModifierIds = [...form.modifier_ids]
        .map((id) => Number(id))
        .filter((id) => Number.isFinite(id) && id > 0)

    const payload = {
        name: form.name,
        description: form.description,
        krypton_menu_id: Number(form.krypton_menu_id),
        is_active: Boolean(form.is_active),
        sort_order: Number(form.sort_order),
        modifiers: orderedModifierIds.map((id, index) => ({
            krypton_menu_id: id,
            sort_order: index,
            description: form.modifier_descriptions[id] ?? null,
        })),
    }

    if (editingId.value) {
        form.transform(() => payload).put(route('packages.update', editingId.value), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('Package updated.')
                closeDialog()
            },
            onError: () => toast.error('Failed to update package.'),
        })
        return
    }

    form.transform(() => payload).post(route('packages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Package created.')
            closeDialog()
        },
        onError: () => toast.error('Failed to create package.'),
    })
}

function confirmDelete(item: PackageVm) {
    pendingDelete.value = item
}

function executeDelete() {
    if (!pendingDelete.value) return
    const item = pendingDelete.value
    pendingDelete.value = null
    router.delete(route('packages.destroy', item.id), {
        preserveScroll: true,
        onSuccess: () => toast.success(`Package "${packageDisplayName(item)}" deleted.`),
        onError: () => toast.error('Failed to delete package.'),
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
                            Guest packages
                        </span>
                        <h2 class="font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            Packages
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{ activeCount }} active package{{ activeCount !== 1 ? 's' : '' }} ·
                            {{ orderedPackages.length === 0 ? '' : allPublished ? 'All published' : 'Some inactive' }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button size="sm" @click="openCreate">
                            <Plus class="mr-1.5 h-3.5 w-3.5" />
                            New Package
                        </Button>
                    </div>
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
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-foreground">{{ packageDisplayName(item) }}</h3>
                                    <span class="inline-flex rounded-full border border-border/60 bg-muted/40 px-2 py-0.5 text-[9px] font-semibold tracking-wide text-muted-foreground uppercase">
                                        from Krypton POS
                                    </span>
                                </div>
                                <p v-if="item.description" class="mt-1 text-sm text-muted-foreground">{{ item.description }}</p>
                            </div>

                            <div v-if="menuPriceForId(item.krypton_menu_id) !== null" class="flex items-baseline gap-1">
                                <span class="font-mono text-3xl font-semibold text-woosoo-accent">₱{{ formatPrice(menuPriceForId(item.krypton_menu_id)) }}</span>
                            </div>

                            <div v-if="(modifierLabelsByPackageId.get(item.id) ?? []).length > 0">
                                <p class="mb-2 text-[10px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                                    Cuts · {{ (modifierLabelsByPackageId.get(item.id) ?? []).length }}
                                </p>
                                <ul class="space-y-1">
                                    <li
                                        v-for="(name, idx) in (modifierLabelsByPackageId.get(item.id) ?? [])"
                                        :key="`${item.id}-mod-${idx}`"
                                        class="flex items-center gap-2 text-sm text-foreground"
                                    >
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-woosoo-accent" />
                                        {{ name }}
                                    </li>
                                </ul>
                            </div>
                            <div v-else class="rounded-lg border border-dashed border-border/60 py-4 text-center text-xs text-muted-foreground">
                                No modifier cuts linked
                            </div>

                            <div class="flex items-center justify-between border-t border-black/5 pt-3 dark:border-white/8">
                                <Badge :variant="item.is_active ? 'default' : 'secondary'" class="text-[10px]">
                                    {{ item.is_active ? 'Published' : 'Inactive' }}
                                </Badge>
                                <div class="flex gap-1">
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
                        <Label for="krypton_menu_id">Package Menu (POS)</Label>
                        <Select v-model="form.krypton_menu_id">
                            <SelectTrigger id="krypton_menu_id">
                                <SelectValue placeholder="Select package menu" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectLabel>Available Menus</SelectLabel>
                                <SelectItem v-for="menu in (menuOptions ?? [])" :key="menu.id" :value="menu.id">
                                    {{ menuSelectLabel(menu) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p class="text-xs text-muted-foreground">The POS menu defines the package name and price.</p>
                        <p v-if="form.errors.krypton_menu_id" class="text-xs text-destructive">{{ form.errors.krypton_menu_id }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="package_name">Display Name</Label>
                        <Input id="package_name" v-model="form.name" placeholder="Auto-filled from POS menu" required />
                        <p class="text-xs text-muted-foreground">Optional override; defaults to the linked POS menu name.</p>
                        <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="package_description">Description</Label>
                        <Textarea
                            id="package_description"
                            v-model="form.description"
                            rows="3"
                            placeholder="Guest-facing description of what this package includes."
                        />
                        <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <Label for="sort_order">Display Order</Label>
                        <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
                        <p v-if="form.errors.sort_order" class="text-xs text-destructive">{{ form.errors.sort_order }}</p>
                    </div>

                    <div class="grid gap-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <Label for="modifier_filter">Modifier Cuts</Label>
                            <Badge variant="secondary">{{ form.modifier_ids.length }} selected</Badge>
                        </div>
                        <Input id="modifier_filter" v-model="modifierSearch" placeholder="Search by name, receipt code, or ID" />
                        <div class="max-h-36 space-y-2 overflow-y-auto rounded-md border p-2">
                            <div v-for="menu in filteredModifierOptions" :key="menu.id" class="flex items-start gap-2 rounded-sm px-1 py-1 hover:bg-muted/60">
                                <Checkbox
                                    :id="`modifier-${menu.id}`"
                                    :model-value="isModifierSelected(menu.id)"
                                    @update:model-value="(value: boolean | 'indeterminate') => toggleModifier(menu.id, value === true)"
                                />
                                <Label :for="`modifier-${menu.id}`" class="cursor-pointer leading-tight">
                                    <span class="font-medium">{{ menu.name }}</span>
                                    <span class="ml-1 text-xs text-muted-foreground">(ID: {{ menu.id }})</span>
                                </Label>
                            </div>
                            <p v-if="filteredModifierOptions.length === 0" class="py-4 text-center text-xs text-muted-foreground">
                                No menu items matched your search.
                            </p>
                        </div>
                    </div>

                    <div v-if="selectedModifiers.length > 0" class="grid gap-2">
                        <Label>Modifier Descriptions</Label>
                        <p class="text-xs text-muted-foreground">Shared across all packages that include each cut.</p>
                        <div class="max-h-40 space-y-3 overflow-y-auto rounded-md border p-3">
                            <div v-for="menu in selectedModifiers" :key="`desc-${menu.id}`" class="space-y-1">
                                <Label :for="`modifier-desc-${menu.id}`" class="text-sm font-medium">{{ menu.name }}</Label>
                                <Textarea
                                    :id="`modifier-desc-${menu.id}`"
                                    v-model="form.modifier_descriptions[menu.id]"
                                    rows="2"
                                    placeholder="Describe this cut for guests."
                                />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <Switch id="is_active" :model-value="form.is_active" @update:model-value="(v) => form.is_active = Boolean(v)" />
                        <Label for="is_active">Published — visible to ordering devices</Label>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" type="button" @click="closeDialog">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : (editingId ? 'Update Package' : 'Create Package') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete confirmation -->
        <AlertDialog :open="!!pendingDelete" @update:open="(v) => { if (!v) pendingDelete = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete package?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently remove <strong>{{ pendingDelete ? packageDisplayName(pendingDelete) : '' }}</strong> and unlink its modifier cuts.
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
