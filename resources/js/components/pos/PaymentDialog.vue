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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import type { PosOrder } from '@/types/pos'

interface Props {
    open: boolean
    order: PosOrder | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
    (e: 'update:open', value: boolean): void
    (e: 'pay', amount: number, paymentTypeId: number, tip?: number): void
}>()

const amount = ref<number>(0)
const paymentTypeId = ref<string>('1')
const tip = ref<number>(0)

watch(() => props.order, (order) => {
    if (order) {
        const total = Number(order.total_amount || 0)
        const paid = Number(order.paid_amount || 0)
        const remaining = Math.max(total - paid, 0)
        amount.value = remaining || total || 0
        paymentTypeId.value = '1'
        tip.value = 0
    }
}, { immediate: true })

const paymentTypes = [
    { id: '1', label: 'Cash' },
    { id: '2', label: 'Credit' },
    { id: '3', label: 'Debit' },
    { id: '4', label: 'GCASH' },
    { id: '5', label: 'PAYMAYA' },
]

const handlePay = () => {
    if (!amount.value || amount.value <= 0) return
    emit('pay', amount.value, parseInt(paymentTypeId.value), tip.value || 0)
}

const handleOpenChange = (value: boolean) => {
    emit('update:open', value)
}
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="sm:max-w-[425px]">
            <DialogHeader>
                <DialogTitle>Pay Order #{{ order?.id }}</DialogTitle>
                <DialogDescription>
                    Total: {{ Number(order?.total_amount || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) }}<br>
                    Paid: {{ Number(order?.paid_amount || 0).toLocaleString('en-PH', { style: 'currency', currency: 'PHP' }) }}
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="amount">Payment Amount</Label>
                    <Input
                        id="amount"
                        v-model.number="amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="w-full"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="paymentType">Payment Type</Label>
                    <Select v-model="paymentTypeId">
                        <SelectTrigger id="paymentType" class="w-full">
                            <SelectValue placeholder="Select payment type" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="pt in paymentTypes"
                                :key="pt.id"
                                :value="pt.id"
                            >
                                {{ pt.label }} ({{ pt.id }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-2">
                    <Label for="tip">Tip (Optional)</Label>
                    <Input
                        id="tip"
                        v-model.number="tip"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full"
                        placeholder="0.00"
                    />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleOpenChange(false)">
                    Cancel
                </Button>
                <Button @click="handlePay">
                    Confirm Payment
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
