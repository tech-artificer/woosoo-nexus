<script setup lang="ts">
import { ref, watch } from 'vue'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'

const props = defineProps<{
  open: boolean
  table?: string
}>()

const emit = defineEmits<{
  'update:open': [value: boolean]
  confirm: [reason: string]
}>()

const REASONS = ['Guest cancelled', 'Allergy conflict', 'Wrong table', 'Kitchen error', 'Other'] as const

const selectedReason = ref<string | null>(null)

// Reset the selection whenever the modal opens so a stale pick never carries over.
watch(
  () => props.open,
  (isOpen) => {
    if (isOpen) {
      selectedReason.value = null
    }
  },
)

function onOpenChange(value: boolean): void {
  emit('update:open', value)
}

function confirmVoid(): void {
  if (!selectedReason.value) {
    return
  }

  emit('confirm', selectedReason.value)
}
</script>

<template>
  <Dialog :open="open" @update:open="onOpenChange">
    <DialogContent class="kds-void-modal">
      <DialogHeader>
        <DialogTitle>Void order</DialogTitle>
        <DialogDescription>
          <template v-if="table">Voiding order for table {{ table }}. </template>
          Select a reason. This removes the order from the active queue and cannot be undone.
        </DialogDescription>
      </DialogHeader>

      <fieldset class="kds-void-reasons">
        <legend class="sr-only">Void reason</legend>
        <label v-for="reason in REASONS" :key="reason" class="kds-void-reason-option">
          <input
            type="radio"
            name="kds-void-reason"
            :value="reason"
            v-model="selectedReason"
          />
          <span>{{ reason }}</span>
        </label>
      </fieldset>

      <DialogFooter class="gap-2">
        <Button variant="secondary" @click="onOpenChange(false)">Cancel</Button>
        <Button variant="destructive" :disabled="!selectedReason" @click="confirmVoid">
          Confirm Void
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>

<style scoped>
.kds-void-reasons {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin: 0;
  padding: 0;
  border: 0;
}

.kds-void-reason-option {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
  padding: 0 8px;
  border-radius: 8px;
  cursor: pointer;
  font-size: 15px;
}

.kds-void-reason-option:hover {
  background: rgb(0 0 0 / 0.04);
}

.kds-void-reason-option input {
  width: 18px;
  height: 18px;
}
</style>
