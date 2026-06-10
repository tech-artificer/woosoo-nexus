<script setup lang="ts">
import { ArrowRight, Check } from 'lucide-vue-next'
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { elapsedFor, formatElapsed, isAdvanceBlocked, isTerminal, nextStateFor, stateLabel, ticketTypeLabel, urgencyFor } from './kdsHelpers'
import type { KdsDensity, KdsTicket } from './kdsTypes'

const props = defineProps<{
  ticket: KdsTicket
  now: number
  density: KdsDensity
}>()

const emit = defineEmits<{
  advance: [ticketId: string]
  toggleItem: [ticketId: string, itemId: string]
}>()

const doneCount = computed(() => props.ticket.items.filter((item) => item.done).length)
const totalCount = computed(() => props.ticket.items.length)
const terminal = computed(() => isTerminal(props.ticket.state))
const elapsed = computed(() => elapsedFor(props.ticket, props.now))
const urgency = computed(() => urgencyFor(props.ticket, props.now))
const nextState = computed(() => nextStateFor(props.ticket.state))
const advanceBlocked = computed(() => isAdvanceBlocked(props.ticket))
const actionLabel = computed(() => {
  if (props.ticket.state === 'new') return 'Start Preparing'
  if (props.ticket.state === 'preparing' || props.ticket.state === 'ready') return 'Mark as Served'
  return ''
})

function splitSafetyName(name: string) {
  const delimiter = name.includes(' - ') ? ' - ' : ' — '
  const parts = name.split(delimiter)

  if (parts.length < 2) {
    return { base: name, modifier: '' }
  }

  return {
    base: parts[0],
    modifier: parts.slice(1).join(delimiter),
  }
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

    <p v-if="ticket.state === 'voided' && ticket.voidReason" class="kds-void-reason">
      {{ ticket.voidReason }}
    </p>

    <section class="kds-items" :aria-label="`Items for order ${ticket.id}`">
      <div class="kds-items-head">
        <span>Items</span>
        <span>{{ doneCount }} / {{ totalCount }} checked</span>
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
        <span class="kds-item-name">
          <template v-if="item.safety">
            <span>{{ splitSafetyName(item.name).base }}</span>
            <span class="kds-safety"> - {{ splitSafetyName(item.name).modifier }}</span>
          </template>
          <template v-else>{{ item.name }}</template>
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
    </footer>
  </article>
</template>
