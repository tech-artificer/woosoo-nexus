<script setup lang="ts">
import { computed } from 'vue'
import { Plus } from 'lucide-vue-next'
import type { PosTable } from '@/types/pos'
import { Button } from '@/components/ui/button'

const DINING_TARGET_MINUTES = 90

function elapsedFromOpened(opened: string | null | undefined): { label: string; minutes: number } {
    if (!opened) return { label: '—', minutes: 0 }
    const diffMs = Date.now() - new Date(opened).getTime()
    if (!Number.isFinite(diffMs) || diffMs < 0) return { label: '—', minutes: 0 }
    const mins = Math.floor(diffMs / 60000)
    if (mins < 60) return { label: `${mins}m`, minutes: mins }
    const hrs = Math.floor(mins / 60)
    const rem = mins % 60
    return { label: rem > 0 ? `${hrs}h ${rem}m` : `${hrs}h`, minutes: mins }
}

interface Props {
    table: PosTable
    selected?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
    (e: 'select', table: PosTable): void
    (e: 'new-order', table: PosTable): void
}>()

const isOpen = computed(() => Boolean(Number(props.table.is_occupied)))

const tableNumber = computed(() => {
    const name = String(props.table.name ?? '')
    const match = name.match(/\d+/)
    return match?.[0] ?? props.table.id
})

const packageName = computed(() => {
    const t = props.table as PosTable & { package_name?: string; package?: { name?: string } }
    return t.package_name ?? t.package?.name ?? '—'
})

const guestCount = computed(() => {
    const t = props.table as PosTable & { guest_count?: number | string }
    const raw = t.guest_count ?? props.table.open_orders_count ?? 0
    return Number(raw) || 0
})

const elapsed = computed(() => elapsedFromOpened(props.table.order_created_in))

const elapsedLabel = computed(() => elapsed.value.label)

const elapsedMinutes = computed(() => elapsed.value.minutes)

const progressPct = computed(() =>
    Math.min(100, Math.round((elapsedMinutes.value / DINING_TARGET_MINUTES) * 100)),
)

const handleCardClick = () => {
    emit('select', props.table)
}

const handleNewOrder = (event: Event) => {
    event.stopPropagation()
    emit('new-order', props.table)
}
</script>

<template>
    <button
        type="button"
        class="group relative flex flex-col gap-3 rounded-[18px] border p-4 text-left transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
        :class="[
            isOpen
                ? 'border-woosoo-accent/40 bg-woosoo-accent/5'
                : 'border-black/8 bg-white/60 dark:border-white/10 dark:bg-white/[0.04]',
            selected ? 'ring-2 ring-woosoo-accent/60' : '',
        ]"
        @click="handleCardClick"
    >
        <div class="flex items-start justify-between gap-2">
            <span class="font-mono text-lg font-bold text-foreground">T-{{ tableNumber }}</span>
            <span
                class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                :class="isOpen
                    ? 'bg-woosoo-accent/15 text-woosoo-primary-dark'
                    : 'bg-muted text-muted-foreground'"
            >
                {{ isOpen ? 'Open' : 'Closed' }}
            </span>
        </div>

        <p class="truncate text-sm font-medium text-foreground">{{ packageName }}</p>
        <p class="text-xs text-muted-foreground">
            {{ guestCount }} guests · {{ elapsedLabel }}
        </p>

        <div v-if="isOpen" class="space-y-1">
            <div class="h-1.5 overflow-hidden rounded-full bg-black/10 dark:bg-white/10">
                <div
                    class="h-full rounded-full bg-woosoo-accent transition-all"
                    :style="{ width: `${progressPct}%` }"
                />
            </div>
            <p class="text-[9px] text-muted-foreground">{{ elapsedMinutes }} / {{ DINING_TARGET_MINUTES }} min</p>
        </div>

        <Button
            v-if="isOpen"
            type="button"
            variant="outline"
            size="sm"
            class="mt-auto h-8 w-full text-xs"
            @click="handleNewOrder"
        >
            <Plus class="mr-1 h-3 w-3" />
            New Order
        </Button>
    </button>
</template>
