<script setup lang="ts">
import { ref, watch } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import type { PosOrder } from '@/types/pos'

interface Props {
    open: boolean
    order: PosOrder | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
    (e: 'save', guestCount: number, reference: string | null): void
}>()

const guestCount = ref<number>(1)
const reference = ref<string>('')

watch(() => props.order, (order) => {
    if (order) {
        guestCount.value = Number(order.guest_count ?? 1)
        reference.value = order.reference || ''
    }
}, { immediate: true })

const handleSave = () => {
    if (!guestCount.value || guestCount.value < 1) return
    emit('save', guestCount.value, reference.value || null)
}

const handleOpenChange = (value: boolean) => {
    emit('update:open', value)
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Edit Order #{{ order?.id }}</DialogTitle>
                <DialogDescription>
                    Update guest count and reference for this order.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="guestCount">Guest Count</Label>
                    <Input
                        id="guestCount"
                        v-model.number="guestCount"
                        type="number"
                        min="1"
                        class="w-full"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="reference">Reference (Optional)</Label>
                    <Input
                        id="reference"
                        v-model="reference"
                        type="text"
                        class="w-full"
                        placeholder="Enter reference..."
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleOpenChange(false)">
                    Cancel
                </Button>
                <Button @click="handleSave">
                    Save Changes
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
