<script setup lang="ts">
import { ArrowRight, Ban, Check, RotateCcw } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { canRecallTicket, canVoidTicket, elapsedFor, formatElapsed, isAdvanceBlocked, isTerminal, nextStateFor, stateLabel, ticketTypeLabel, urgencyFor } from './kdsHelpers'
import type { KdsDensity, KdsTicket } from './kdsTypes'

const props = defineProps<{
  ticket: KdsTicket
  now: number
  clockOffset: number
  density: KdsDensity
}>()

const emit = defineEmits<{
  advance: [ticketId: string]
  recall: [ticketId: string]
  toggleItem: [ticketId: string, itemId: string]
  void: [ticketId: string, reason: string]
}>()

const doneCount = computed(() => props.ticket.items.filter((item) => item.done).length)
const totalCount = computed(() => props.ticket.items.length)
const terminal = computed(() => isTerminal(props.ticket.state))
const elapsed = computed(() => elapsedFor(props.ticket, props.now, props.clockOffset))
const urgency = computed(() => urgencyFor(props.ticket, props.now, props.clockOffset))
const nextState = computed(() => nextStateFor(props.ticket.state))
const advanceBlocked = computed(() => isAdvanceBlocked(props.ticket))
const recallable = computed(() => canRecallTicket(props.ticket))
const voidable = computed(() => canVoidTicket(props.ticket))
const actionLabel = computed(() => {
  if (props.ticket.state === 'new') return 'Start Preparing'
  if (props.ticket.state === 'preparing' || props.ticket.state === 'ready') return 'Mark as Served'
  return ''
})

const VOID_REASONS = ['Guest cancelled', 'Allergy conflict', 'Wrong table', 'Kitchen error', 'Other']
const showVoidDialog = ref(false)
const voidReason = ref('')

function openVoidDialog() {
  voidReason.value = ''
  showVoidDialog.value = true
}

function confirmVoid() {
  if (!voidReason.value) return
  emit('void', props.ticket.id, voidReason.value)
  showVoidDialog.value = false
}
</script>

<template>
  <article
    class="kds-ticket"
    :class="[
      `state-${ticket.state}`,
      `urgency-${urgency}`,
      `density-${density}`,
      { 'is-terminal': terminal },
    ]"
  >
    <header class="kds-ticket-header">
      <div class="kds-ticket-pane">
        <span>Table</span>
        <strong :class="{ 'is-struck': ticket.state === 'voided' }">{{ ticket.table }}</strong>
        <small>Order {{ ticket.id }}</small>
      </div>
      <div class="kds-ticket-pane is-right">
        <span>Elapsed</span>
        <strong class="kds-timer">{{ formatElapsed(elapsed) }}</strong>
        <small>Issued {{ ticket.issued }}</small>
      </div>
    </header>

    <div class="kds-ticket-type-row">
      <Badge
        variant="outline"
        class="kds-pill"
        :class="{ 'is-refill': ticket.type === 'refill' }"
      >
        {{ ticketTypeLabel(ticket.type) }}
      </Badge>
      <Badge v-if="ticket.recalled" variant="warning" class="kds-recalled">
        Recalled x{{ ticket.recalled }}
      </Badge>
    </div>

    <p v-if="ticket.packageName" class="kds-package-info">
      {{ ticket.packageName }}<span v-if="ticket.guestCount"> · {{ ticket.guestCount }} guests</span>
    </p>

    <p v-if="ticket.state === 'voided' && ticket.voidReason" class="kds-void-reason">
      {{ ticket.voidReason }}
    </p>

    <section class="kds-items" :aria-label="`Items for order ${ticket.id}`">
      <div class="kds-items-head">
        <span>Items</span>
        <span
          class="kds-items-progress"
          :class="{
            'has-progress': doneCount > 0,
            'is-complete': totalCount > 0 && doneCount === totalCount,
          }"
        >
          {{ doneCount }} / {{ totalCount }} checked
        </span>
      </div>

      <button
        v-for="item in ticket.items"
        :key="item.id"
        type="button"
        class="kds-item-row"
        :class="{ 'is-done': item.done, 'is-disabled': terminal }"
        :disabled="terminal"
        @click="emit('toggleItem', ticket.id, item.id)"
      >
        <Checkbox
          :checked="item.done"
          :disabled="terminal"
          class="kds-item-check"
          aria-hidden="true"
          tabindex="-1"
        />
        <span class="kds-item-qty">{{ item.qty }}x</span>
        <span class="kds-item-text">
          <span class="kds-item-name" :class="{ 'is-done': item.done }">
            {{ item.name }}
          </span>
          <span v-if="item.notes" class="kds-item-note">{{ item.notes }}</span>
        </span>
      </button>
    </section>

    <footer class="kds-ticket-footer">
      <div class="kds-status-block">
        <span>Status</span>
        <Badge variant="outline" class="kds-status-badge" :class="`state-${ticket.state}`">
          <Check v-if="ticket.state === 'served'" aria-hidden="true" />
          {{ stateLabel(ticket.state) }}
        </Badge>
      </div>

      <Button
        v-if="nextState"
        type="button"
        variant="brand"
        class="kds-card-action"
        :disabled="advanceBlocked"
        @click="emit('advance', ticket.id)"
      >
        {{ actionLabel }}
        <ArrowRight data-icon="inline-end" aria-hidden="true" />
      </Button>

      <Button
        v-else-if="recallable"
        type="button"
        variant="outline"
        class="kds-card-action kds-recall-action"
        @click="emit('recall', ticket.id)"
      >
        <RotateCcw data-icon="inline-start" aria-hidden="true" />
        Recall
      </Button>

      <Button
        v-if="voidable"
        type="button"
        variant="outline"
        class="kds-void-action"
        aria-label="Void order"
        @click="openVoidDialog"
      >
        <Ban aria-hidden="true" />
      </Button>
    </footer>

    <Dialog :open="showVoidDialog" @update:open="showVoidDialog = $event">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Void Order — Table {{ ticket.table }}</DialogTitle>
          <DialogDescription>Select a reason. This cannot be undone.</DialogDescription>
        </DialogHeader>
        <div class="flex flex-col gap-2">
          <label v-for="reason in VOID_REASONS" :key="reason" class="flex items-center gap-2">
            <input v-model="voidReason" type="radio" name="void-reason" :value="reason" />
            {{ reason }}
          </label>
        </div>
        <DialogFooter>
          <Button type="button" variant="outline" @click="showVoidDialog = false">Cancel</Button>
          <Button type="button" class="bg-red-600 text-white hover:bg-red-500" :disabled="!voidReason" @click="confirmVoid">
            Confirm Void
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </article>
</template>
