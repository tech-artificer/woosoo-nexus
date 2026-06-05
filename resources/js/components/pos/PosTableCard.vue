<script setup lang="ts">
import { TableProperties } from 'lucide-vue-next'
import type { PosTable } from '@/types/pos'

interface Props {
    table: PosTable
    selected?: boolean
}

const props = defineProps<Props>()
const emit = defineEmits<{
    (e: 'select', table: PosTable): void
}>()

const handleClick = () => {
    emit('select', props.table)
}
</script>

<template>
    <button
        type="button"
        class="group relative rounded-2xl border p-4 text-left transition-all duration-200"
        :class="[
            Number(table.is_occupied)
                ? 'border-destructive/40 bg-destructive/10'
                : 'border-woosoo-green/30 bg-woosoo-green/10',
            selected ? 'ring-2 ring-primary/70' : '',
            'hover:border-primary hover:shadow-lg hover:-translate-y-0.5'
        ]"
        @click="handleClick"
    >
        <!-- Status indicator -->
        <div class="mb-2 flex items-center justify-between">
            <TableProperties class="h-4 w-4 text-foreground/70" />
            <span
                class="text-[10px] font-semibold uppercase tracking-wide"
                :class="Number(table.is_occupied) ? 'text-destructive' : 'text-woosoo-green'"
            >
                {{ Number(table.is_occupied) ? 'Occupied' : 'Available' }}
            </span>
        </div>

        <!-- Table name -->
        <p class="text-base font-semibold">{{ table.name }}</p>
        <p class="text-[11px] text-muted-foreground">ID {{ table.id }}</p>

        <!-- Order count -->
        <p class="mt-2 text-xs text-muted-foreground">
            Orders: <span class="font-semibold text-foreground">{{ Number(table.open_orders_count) }}</span>
        </p>

        <!-- Occupied pulse indicator (subtle, respects reduced-motion) -->
        <div
            v-if="Number(table.is_occupied)"
            class="absolute right-3 top-3 flex h-2 w-2"
            aria-hidden="true"
        >
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-destructive opacity-75"></span>
            <span class="relative inline-flex h-2 w-2 rounded-full bg-destructive"></span>
        </div>
    </button>
</template>
