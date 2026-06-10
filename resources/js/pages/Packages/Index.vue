<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import { Pencil, Trash2, Package, RotateCcw } from 'lucide-vue-next'
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
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
    { title: 'Krypton Modifiers', href: route('packages.index') },
]

const editingId = ref<number | null>(null)
const pendingDelete = ref<PackageVm | null>(null)

const form = useForm({
    name: '',
    description: '',
    krypton_menu_id: 0,
    is_active: true,
    sort_order: 0,
    modifier_ids: [] as number[],
    modifier_descriptions: {} as Record<number, string>,
})

const modifierSearch = ref('')

const orderedPackages = computed(() => {
    return [...(props.packages ?? [])].sort((a, b) => a.sort_order - b.sort_order)
})

function modifierNames(modifiers: PackageModifierVm[]): string {
    const byId = new Map((props.menuOptions ?? []).map((m) => [m.id, m.name]))
    const names = [...modifiers]
        .sort((a, b) => a.sort_order - b.sort_order)
        .map((m) => byId.get(m.krypton_menu_id) ?? `#${m.krypton_menu_id}`)
    if (names.length === 0) return '—'
    const joined = names.join(', ')
    return joined.length > 60 ? joined.slice(0, 57) + '…' : joined
}

const packageMenuOptions = computed(() => {
    return props.menuOptions ?? []
})

const filteredModifierOptions = computed(() => {
    const needle = modifierSearch.value.trim().toLowerCase()
    if (!needle) {
        return props.menuOptions ?? []
    }
    return (props.menuOptions ?? []).filter((item) => {
        return item.name.toLowerCase().includes(needle)
            || String(item.id).includes(needle)
            || (item.receipt_name ?? '').toLowerCase().includes(needle)
    })
})

const selectedModifierPreview = computed(() => {
    const picked = new Set(form.modifier_ids)
    return (props.menuOptions ?? []).filter((item) => picked.has(item.id)).slice(0, 8)
})

const selectedModifiers = computed(() => {
    const byId = new Map((props.menuOptions ?? []).map((item) => [item.id, item]))
    return form.modifier_ids
        .map((id) => byId.get(id) ?? { id, name: `Menu #${id}`, receipt_name: null, is_modifier_only: false } as MenuOption)
})

function isModifierSelected(menuId: number): boolean {
    return form.modifier_ids.includes(menuId)
}

function toggleModifier(menuId: number, checked: boolean): void {
    if (checked && !form.modifier_ids.includes(menuId)) {
        form.modifier_ids = [...form.modifier_ids, menuId]
        return
    }
    if (!checked && form.modifier_ids.includes(menuId)) {
        form.modifier_ids = form.modifier_ids.filter((id) => id !== menuId)
    }
}

function resetForm() {
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

function editPackage(item: PackageVm) {
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
    window.scrollTo({ top: 0, behavior: 'smooth' })
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
                resetForm()
            },
            onError: () => toast.error('Failed to update package.'),
        })
        return
    }

    form.transform(() => payload).post(route('packages.store'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success('Package created.')
            resetForm()
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
        onSuccess: () => toast.success(`Package "${item.name}" deleted.`),
        onError: () => toast.error('Failed to delete package.'),
    })
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="title" />

        <div class="space-y-5">
            <!-- Create / Edit form section -->
            <section class="relative overflow-hidden rounded-[26px] border border-black/8 bg-card/92 px-5 py-6 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10 md:px-6">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-[#f6b56d]/10 via-transparent to-transparent dark:from-[#f6b56d]/6" />
                <div class="relative space-y-5">
                    <!-- Section header -->
                    <div>
                        <a :href="route('package-configs.index')" class="mb-2 inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground">
                            ← Dining Tiers
                        </a>
                        <div class="mt-1">
                            <span class="inline-flex rounded-full border border-border/70 bg-accent/12 px-3 py-1 text-[11px] font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                Krypton Modifiers
                            </span>
                        </div>
                        <h2 class="mt-2 font-header text-2xl font-semibold tracking-tight text-foreground sm:text-3xl">
                            {{ editingId ? 'Edit Package' : 'New Package' }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ editingId ? 'Update package details and linked menu items.' : 'Add a new menu package and map its modifier items.' }}
                        </p>
                    </div>

                    <!-- Form -->
                    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
                        <div class="space-y-2">
                            <Label for="package_name">Package Name</Label>
                            <Input id="package_name" v-model="form.name" placeholder="Set Meal A" required />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="package_description">Description</Label>
                            <Textarea
                                id="package_description"
                                v-model="form.description"
                                rows="3"
                                placeholder="A short, guest-facing description of what this package includes."
                            />
                            <p class="text-xs text-muted-foreground">Shown to guests. Describe what makes this package worth choosing.</p>
                            <p v-if="form.errors.description" class="text-sm text-destructive">{{ form.errors.description }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="krypton_menu_id">Package Menu</Label>
                            <Select v-model="form.krypton_menu_id">
                                <SelectTrigger id="krypton_menu_id">
                                    <SelectValue placeholder="Select package menu" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectLabel>Available Menus</SelectLabel>
                                    <SelectItem v-for="menu in packageMenuOptions" :key="menu.id" :value="menu.id">
                                        {{ menu.name }} (ID: {{ menu.id }})
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-xs text-muted-foreground">Choose the real menu entry linked to this package.</p>
                            <p v-if="form.errors.krypton_menu_id" class="text-sm text-destructive">{{ form.errors.krypton_menu_id }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="sort_order">Display Order</Label>
                            <Input id="sort_order" v-model.number="form.sort_order" type="number" min="0" />
                            <p class="text-xs text-muted-foreground">Lower numbers appear first.</p>
                            <p v-if="form.errors.sort_order" class="text-sm text-destructive">{{ form.errors.sort_order }}</p>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-2">
                                <Label for="modifier_filter">Modifier Menus</Label>
                                <Badge variant="secondary">{{ form.modifier_ids.length }} selected</Badge>
                            </div>
                            <Input id="modifier_filter" v-model="modifierSearch" placeholder="Search menu by name, receipt code, or ID" />
                            <div class="max-h-44 space-y-2 overflow-y-auto rounded-md border p-2">
                                <div v-for="menu in filteredModifierOptions" :key="menu.id" class="flex items-start gap-2 rounded-sm px-1 py-1 hover:bg-muted/60">
                                    <Checkbox
                                        :id="`modifier-${menu.id}`"
                                        :model-value="isModifierSelected(menu.id)"
                                        @update:model-value="(value: boolean | 'indeterminate') => toggleModifier(menu.id, value === true)"
                                    />
                                    <Label :for="`modifier-${menu.id}`" class="cursor-pointer leading-tight">
                                        <span class="font-medium">{{ menu.name }}</span>
                                        <span class="ml-1 text-xs text-muted-foreground">(ID: {{ menu.id }})</span>
                                        <span v-if="menu.receipt_name" class="ml-1 text-xs text-muted-foreground">{{ menu.receipt_name }}</span>
                                    </Label>
                                </div>
                                <p v-if="filteredModifierOptions.length === 0" class="py-6 text-center text-xs text-muted-foreground">
                                    No menu items matched your search.
                                </p>
                            </div>
                            <div v-if="selectedModifierPreview.length > 0" class="flex flex-wrap gap-1">
                                <Badge v-for="menu in selectedModifierPreview" :key="`picked-${menu.id}`" variant="outline">
                                    {{ menu.name }}
                                </Badge>
                            </div>
                        </div>

                        <div v-if="selectedModifiers.length > 0" class="space-y-3 md:col-span-2">
                            <div class="flex items-center justify-between gap-2">
                                <Label>Modifier Descriptions</Label>
                                <Badge variant="secondary">{{ selectedModifiers.length }} item{{ selectedModifiers.length === 1 ? '' : 's' }}</Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                Descriptions are shared across all packages that include this item.
                            </p>
                            <div class="space-y-3 rounded-md border p-3">
                                <div v-for="menu in selectedModifiers" :key="`desc-${menu.id}`" class="space-y-1">
                                    <Label :for="`modifier-desc-${menu.id}`" class="leading-tight">
                                        <span class="font-medium">{{ menu.name }}</span>
                                        <span class="ml-1 text-xs text-muted-foreground">(ID: {{ menu.id }})</span>
                                        <span v-if="menu.receipt_name" class="ml-1 text-xs text-muted-foreground">{{ menu.receipt_name }}</span>
                                    </Label>
                                    <Textarea
                                        :id="`modifier-desc-${menu.id}`"
                                        v-model="form.modifier_descriptions[menu.id]"
                                        rows="2"
                                        placeholder="Describe this modifier for guests."
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 md:col-span-2">
                            <Switch id="is_active" :model-value="form.is_active" @update:model-value="(v) => form.is_active = Boolean(v)" />
                            <Label for="is_active">Active — visible to ordering devices</Label>
                        </div>

                        <div class="flex gap-2 md:col-span-2">
                            <Button type="submit" :disabled="form.processing">
                                {{ form.processing ? 'Saving…' : (editingId ? 'Update Package' : 'Create Package') }}
                            </Button>
                            <Button type="button" variant="outline" :disabled="form.processing" @click="resetForm">
                                <RotateCcw class="mr-2 h-4 w-4" />
                                Reset
                            </Button>
                        </div>
                    </form>
                </div>
            </section>

            <!-- Package list section -->
            <section class="overflow-hidden rounded-[26px] border border-black/8 bg-card/92 shadow-sm shadow-black/5 backdrop-blur-sm dark:border-white/10">
                <div class="flex items-center gap-2 border-b border-black/8 px-5 py-4 dark:border-white/10">
                    <Package class="h-4 w-4 text-muted-foreground" />
                    <div>
                        <p class="text-sm font-semibold text-foreground">Configured Packages</p>
                        <p class="text-xs text-muted-foreground">{{ orderedPackages.length }} package{{ orderedPackages.length === 1 ? '' : 's' }} configured.</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Name</TableHead>
                                <TableHead>Menu ID</TableHead>
                                <TableHead>Modifiers</TableHead>
                                <TableHead>Order</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="text-right">Actions</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in orderedPackages" :key="item.id">
                                <TableCell class="font-medium">{{ item.name }}</TableCell>
                                <TableCell class="font-mono text-sm">{{ item.krypton_menu_id }}</TableCell>
                                <TableCell class="text-sm text-muted-foreground">{{ modifierNames(item.modifiers ?? []) }}</TableCell>
                                <TableCell>{{ item.sort_order }}</TableCell>
                                <TableCell>
                                    <Badge :variant="item.is_active ? 'default' : 'secondary'">
                                        {{ item.is_active ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-2">
                                        <Button size="sm" variant="outline" @click="editPackage(item)">
                                            <Pencil class="mr-1 h-3 w-3" />
                                            Edit
                                        </Button>
                                        <Button size="sm" variant="destructive" @click="confirmDelete(item)">
                                            <Trash2 class="mr-1 h-3 w-3" />
                                            Delete
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="orderedPackages.length === 0">
                                <TableCell colspan="6" class="py-12 text-center text-sm text-muted-foreground">
                                    No packages configured yet. Use the form above to add the first one.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </section>
        </div>

        <!-- Delete confirmation dialog -->
        <AlertDialog :open="!!pendingDelete" @update:open="(v) => { if (!v) pendingDelete = null }">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete package?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently remove <strong>{{ pendingDelete?.name }}</strong> and unlink its modifier mappings.
                        Ordering sessions currently using this package will be unaffected.
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
