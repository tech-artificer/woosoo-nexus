<script setup lang="ts">
import { ArrowRight, Check, RotateCcw } from 'lucide-vue-next'
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { canRecallTicket, elapsedFor, formatElapsed, isAdvanceBlocked, isTerminal, nextStateFor, stateLabel, ticketTypeLabel, urgencyFor } from './kdsHelpers'
import type { KdsDensity, KdsItem, KdsTicket } from './kdsTypes'

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
}>()

const doneCount = computed(() => props.ticket.items.filter((item) => item.done).length)
const totalCount = computed(() => props.ticket.items.length)
const terminal = computed(() => isTerminal(props.ticket.state))
const elapsed = computed(() => elapsedFor(props.ticket, props.now, props.clockOffset))
const urgency = computed(() => urgencyFor(props.ticket, props.now, props.clockOffset))
const nextState = computed(() => nextStateFor(props.ticket.state))
const advanceBlocked = computed(() => isAdvanceBlocked(props.ticket))
const recallable = computed(() => canRecallTicket(props.ticket))
const actionLabel = computed(() => {
  if (props.ticket.state === 'new') return 'Start Preparing'
  if (props.ticket.state === 'preparing' || props.ticket.state === 'ready') return 'Mark as Served'
  return ''
})

function canCheck(item: KdsItem): boolean {
  return !terminal.value && props.ticket.state === 'preparing' && !item.done
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
        <div class="kds-card__ref">
          <span class="kds-card__order-number">{{ ticket.orderNumber ?? `Order ${ticket.id}` }}</span>
          <span v-if="ticket.orderNumber" class="kds-card__order-id">#{{ ticket.id }}</span>
        </div>
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
        :class="{ 'is-done': item.done, 'is-disabled': !canCheck(item) }"
        :disabled="!canCheck(item)"
        @click="canCheck(item) && emit('toggleItem', ticket.id, item.id)"
      >
        <Checkbox
          as="span"
          :model-value="item.done"
          :disabled="!canCheck(item)"
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
        :aria-disabled="advanceBlocked ? 'true' : undefined"
        @click="emit('advance', ticket.id)"
      >
        {{ actionLabel }}
        <ArrowRight data-icon="inline-end" aria-hidden="true" />
      </Button>

      <Button
        v-else-if="ticket.state === 'served'"
        type="button"
        variant="outline"
        class="kds-card-action kds-recall-action"
        :aria-disabled="!recallable ? 'true' : undefined"
        @click="emit('recall', ticket.id)"
      >
        <RotateCcw data-icon="inline-start" aria-hidden="true" />
        {{ recallable ? 'Recall' : 'Max Recalls Reached' }}
      </Button>
    </footer>
  </article>
</template>
