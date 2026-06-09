<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { toast } from 'vue-sonner'
import 'vue-sonner/style.css'
import { Toaster } from '@/components/ui/sonner'
import KdsCommandBar from '@/components/KDS/KdsCommandBar.vue'
import KdsEmptyState from '@/components/KDS/KdsEmptyState.vue'
import KdsFilterChips from '@/components/KDS/KdsFilterChips.vue'
import KdsTicketCard from '@/components/KDS/KdsTicketCard.vue'
import { ACTIVE_STATES, applyAdvance, applyRecall, canAdvanceTicket, filterTickets, sortTickets } from '@/components/KDS/kdsHelpers'
import type { KdsDensity, KdsFilter, KdsTicket } from '@/components/KDS/kdsTypes'

const props = defineProps<{
  title: string
  initialTickets: KdsTicket[]
}>()

function seedTickets(source: KdsTicket[]): KdsTicket[] {
  return source.map((ticket) => ({
    ...ticket,
    items: ticket.items.map((item) => ({ ...item, done: item.done === true })),
  }))
}

const tickets = ref<KdsTicket[]>(seedTickets(props.initialTickets))
const selectedFilter = ref<KdsFilter>('active')
const density = ref<KdsDensity>('comfortable')
const now = ref(Date.now())
let timer: ReturnType<typeof setInterval> | null = null

const activeTickets = computed(() => tickets.value.filter((ticket) => ACTIVE_STATES.includes(ticket.state)))
const visibleTickets = computed(() => sortTickets(filterTickets(tickets.value, selectedFilter.value, now.value), now.value))
const clockLabel = computed(() => new Intl.DateTimeFormat('en-US', {
  hour: 'numeric',
  minute: '2-digit',
}).format(now.value))
const dateLabel = computed(() => new Intl.DateTimeFormat('en-US', {
  weekday: 'short',
  month: 'short',
  day: 'numeric',
}).format(now.value))
const counts = computed<Record<KdsFilter, number>>(() => ({
  active: activeTickets.value.length,
  overdue: filterTickets(tickets.value, 'overdue', now.value).length,
  new: tickets.value.filter((ticket) => ticket.state === 'new').length,
  preparing: tickets.value.filter((ticket) => ticket.state === 'preparing').length,
  ready: tickets.value.filter((ticket) => ticket.state === 'ready').length,
  served: tickets.value.filter((ticket) => ticket.state === 'served').length,
  voided: tickets.value.filter((ticket) => ticket.state === 'voided').length,
}))

function updateTicket(ticketId: string, updater: (ticket: KdsTicket) => KdsTicket) {
  tickets.value = tickets.value.map((ticket) => ticket.id === ticketId ? updater(ticket) : ticket)
}

function toggleItem(ticketId: string, itemId: string) {
  updateTicket(ticketId, (ticket) => {
    if (ticket.state === 'served' || ticket.state === 'voided') {
      return ticket
    }

    return {
      ...ticket,
      items: ticket.items.map((item) => item.id === itemId ? { ...item, done: !item.done } : item),
    }
  })
}

function advanceTicket(ticketId: string) {
  const ticket = tickets.value.find((item) => item.id === ticketId)

  if (!ticket) {
    return
  }

  if (!canAdvanceTicket(ticket)) {
    toast.warning('Complete all checklist items first.', {
      duration: 3500,
    })
    return
  }

  updateTicket(ticketId, (current) => applyAdvance(current, now.value))
}

function toggleDensity() {
  density.value = density.value === 'comfortable' ? 'compact' : 'comfortable'
  try {
    window.localStorage.setItem('kds-density', density.value)
  } catch {
    // Storage is optional for kiosk browsers.
  }
}

function recallTicket(ticketId: string) {
  updateTicket(ticketId, (ticket) => applyRecall(ticket, now.value))
  selectedFilter.value = 'active'
  toast.success('Ticket recalled to the line.', {
    duration: 3000,
  })
}

onMounted(() => {
  document.body.classList.add('kds-active')

  try {
    const storedDensity = window.localStorage.getItem('kds-density')
    if (storedDensity === 'comfortable' || storedDensity === 'compact') {
      density.value = storedDensity
    }
  } catch {
    // Storage is optional for kiosk browsers.
  }

  timer = setInterval(() => {
    now.value = Date.now()
  }, 1000)
})

onBeforeUnmount(() => {
  document.body.classList.remove('kds-active')

  if (timer) {
    clearInterval(timer)
    timer = null
  }
})
</script>

<template>
  <Head :title="title" />

  <main class="kds-viewport" aria-label="Woosoo Kitchen Display">
    <section class="kds-shell">
        <KdsCommandBar
          :active="counts.active"
          :new-count="counts.new"
          :preparing="counts.preparing"
          :ready="counts.ready"
          :overdue="counts.overdue"
          :clock="clockLabel"
          :date-label="dateLabel"
        />

        <div class="kds-subbar">
          <KdsFilterChips v-model="selectedFilter" :counts="counts" />
          <button
            type="button"
            class="kds-density-toggle"
            :aria-pressed="density === 'compact'"
            @click="toggleDensity"
          >
            {{ density === 'comfortable' ? 'Comfortable' : 'Compact' }}
          </button>
        </div>

        <section class="kds-grid-wrap" aria-label="Ticket queue">
          <div v-if="visibleTickets.length" class="kds-grid" :class="`density-${density}`">
            <KdsTicketCard
              v-for="ticket in visibleTickets"
              :key="ticket.id"
              :ticket="ticket"
              :now="now"
              :density="density"
              @advance="advanceTicket"
              @recall="recallTicket"
              @toggle-item="toggleItem"
            />
          </div>
          <KdsEmptyState v-else />
        </section>
    </section>
    <Toaster position="top-center" rich-colors />
  </main>
</template>

<style>
:root {
  --kds-bg0: #050506;
  --kds-bg1: #0d0c10;
  --kds-bg2: #151318;
  --kds-bg3: #1d1a21;
  --kds-bg4: #25212a;
  --kds-fg0: #f4efe7;
  --kds-fg1: #ddd4c7;
  --kds-fg2: #a89d8e;
  --kds-fg3: #70675e;
  --kds-accent: #f6b56d;
  --kds-new: #85a9d8;
  --kds-preparing: #de8a48;
  --kds-ready: #6fc778;
  --kds-served: #96918e;
  --kds-warning: #d8a440;
  --kds-overdue: #d65540;
  --kds-refill: #5cb6b0;
  --kds-void: #a892ad;
  --kds-font-d: Raleway, ui-sans-serif, system-ui, sans-serif;
  --kds-font-s: Kanit, ui-sans-serif, system-ui, sans-serif;
  --kds-font-m: "JetBrains Mono", "Roboto Mono", ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  /* Weight scale — see .interface-design/system.md. Capped at 700 to de-shout. */
  --kds-weight-display: 700;
  --kds-weight-hero: 700;
  --kds-weight-cta: 700;
  --kds-weight-label: 600;
  --kds-weight-body: 500;
  --kds-weight-data: 600;
  --kds-weight-caption: 500;
}

body.kds-active {
  overflow: hidden;
  margin: 0;
  width: 100%;
  height: 100%;
  background: var(--kds-bg0);
}
</style>

<style scoped>
.kds-viewport {
  display: flex;
  flex-direction: column;
  width: 100dvw;
  height: 100dvh;
  min-height: 100dvh;
  max-height: 100dvh;
  overflow: hidden;
  background:
    radial-gradient(circle at 18% 0%, rgb(246 181 109 / 0.08), transparent 24%),
    linear-gradient(180deg, #0c0b0f 0%, #040405 100%);
  color: var(--kds-fg0);
  font-family: var(--kds-font-s);
  letter-spacing: 0;
  padding:
    env(safe-area-inset-top, 0)
    env(safe-area-inset-right, 0)
    env(safe-area-inset-bottom, 0)
    env(safe-area-inset-left, 0);
  touch-action: manipulation;
  box-sizing: border-box;
}

.kds-shell {
  display: grid;
  grid-template-rows: minmax(68px, auto) minmax(58px, auto) minmax(0, 1fr);
  flex: 1;
  min-height: 0;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: var(--kds-bg0);
}

.kds-subbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  border-bottom: 1px solid rgb(255 255 255 / 0.08);
  background: #0f0e12;
  padding: 8px;
}

.kds-density-toggle {
  min-height: 44px;
  border: 1px solid rgb(255 255 255 / 0.1);
  border-radius: 8px;
  background: var(--kds-bg2);
  color: var(--kds-fg1);
  font-family: var(--kds-font-s);
  font-size: 12px;
  font-weight: var(--kds-weight-label);
  padding: 0 16px;
  text-transform: uppercase;
}

.kds-density-toggle:focus-visible {
  outline: 3px solid rgb(246 181 109 / 0.45);
  outline-offset: 2px;
}

.kds-grid-wrap {
  min-height: 0;
  overflow: auto;
  background: #030304;
  padding: 14px 8px 18px;
}

.kds-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
}

.kds-grid.density-compact {
  gap: 10px;
}

:deep(.kds-command) {
  display: grid;
  grid-template-columns: 280px 1fr 310px;
  align-items: center;
  border-bottom: 1px solid rgb(255 255 255 / 0.08);
  background: var(--kds-bg1);
  padding: 0 8px;
}

:deep(.kds-brand),
:deep(.kds-status-clock),
:deep(.kds-online),
:deep(.kds-rush),
:deep(.kds-clock),
:deep(.kds-filters),
:deep(.kds-ticket-type-row),
:deep(.kds-ticket-footer),
:deep(.kds-status-block),
:deep(.kds-item-row) {
  display: flex;
  align-items: center;
}

:deep(.kds-brand) {
  gap: 10px;
}

:deep(.kds-brand-mark) {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--kds-accent);
  color: #14100c;
}

:deep(.kds-brand-mark svg) {
  width: 20px;
  height: 20px;
}

:deep(.kds-brand-name) {
  font-family: var(--kds-font-d);
  font-size: 16px;
  font-weight: var(--kds-weight-display);
  line-height: 1;
}

:deep(.kds-brand-sub) {
  color: var(--kds-accent);
  font-size: 11px;
  font-weight: var(--kds-weight-label);
  letter-spacing: 0.14em;
  line-height: 1.15;
  text-transform: uppercase;
}

:deep(.kds-metrics) {
  display: grid;
  grid-template-columns: repeat(5, minmax(80px, 1fr));
  height: 100%;
}

:deep(.kds-metric) {
  display: grid;
  place-items: center;
  align-content: center;
  border-left: 1px solid rgb(255 255 255 / 0.08);
  gap: 4px;
}

:deep(.kds-metric strong) {
  font-family: var(--kds-font-m);
  font-size: 24px;
  font-variant-numeric: tabular-nums;
  font-weight: var(--kds-weight-data);
  line-height: 1;
}

:deep(.kds-metric span) {
  color: var(--kds-fg3);
  font-size: 9px;
  font-weight: var(--kds-weight-label);
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

:deep(.kds-metric .is-new) {
  color: var(--kds-new);
}

:deep(.kds-metric .is-preparing) {
  color: var(--kds-preparing);
}

:deep(.kds-metric .is-ready) {
  color: var(--kds-ready);
}

:deep(.kds-metric .is-overdue) {
  color: var(--kds-overdue);
}

:deep(.kds-status-clock) {
  justify-content: flex-end;
  gap: 12px;
}

:deep(.kds-online),
:deep(.kds-rush) {
  min-height: 30px;
  gap: 7px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: var(--kds-weight-label);
  padding: 0 13px;
  text-transform: uppercase;
}

:deep(.kds-online) {
  border: 1px solid rgb(82 190 102 / 0.35);
  background: rgb(82 190 102 / 0.16);
  color: #7fdb88;
}

:deep(.kds-rush) {
  border: 1px solid rgb(214 85 64 / 0.35);
  background: rgb(214 85 64 / 0.14);
  color: var(--kds-overdue);
}

:deep(.kds-online svg),
:deep(.kds-rush svg),
:deep(.kds-clock svg) {
  width: 16px;
  height: 16px;
}

:deep(.kds-clock) {
  gap: 8px;
  font-family: var(--kds-font-m);
}

:deep(.kds-clock strong) {
  display: block;
  font-size: 22px;
  font-variant-numeric: tabular-nums;
  font-weight: var(--kds-weight-data);
  line-height: 1;
}

:deep(.kds-clock span) {
  display: block;
  color: var(--kds-fg3);
  font-size: 9px;
  font-weight: var(--kds-weight-caption);
  letter-spacing: 0.14em;
  margin-top: 3px;
  text-transform: uppercase;
}

:deep(.kds-filters) {
  gap: 8px;
  min-width: 0;
}

:deep(.kds-filter-divider) {
  width: 1px;
  height: 34px;
  background: rgb(255 255 255 / 0.08);
  margin: 0 6px;
}

:deep(.kds-filter-chip) {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 44px;
  min-width: 116px;
  border: 1px solid rgb(255 255 255 / 0.08);
  border-radius: 8px;
  background: var(--kds-bg2);
  color: var(--kds-fg1);
  font-family: var(--kds-font-s);
  font-size: 13px;
  font-weight: var(--kds-weight-label);
  padding: 0 14px;
  text-transform: uppercase;
}

:deep(.kds-filter-chip strong) {
  display: grid;
  place-items: center;
  min-width: 24px;
  height: 24px;
  border-radius: 999px;
  background: rgb(255 255 255 / 0.06);
  color: var(--kds-fg2);
  font-family: var(--kds-font-m);
  font-size: 12px;
  font-variant-numeric: tabular-nums;
  font-weight: var(--kds-weight-data);
}

:deep(.kds-filter-chip.is-active) {
  border-color: rgb(133 169 216 / 0.45);
  background: rgb(30 57 88 / 0.45);
  color: var(--kds-fg0);
}

:deep(.kds-filter-chip:focus-visible) {
  outline: 3px solid rgb(246 181 109 / 0.45);
  outline-offset: 2px;
}

:deep(.kds-ticket) {
  display: flex;
  min-height: 312px;
  overflow: hidden;
  flex-direction: column;
  border: 1px solid rgb(255 255 255 / 0.08);
  border-left-width: 4px;
  border-radius: 10px;
  background: var(--kds-bg2);
  box-shadow: 0 18px 44px rgb(0 0 0 / 0.32);
}

:deep(.kds-ticket.density-compact) {
  min-height: 248px;
}

:deep(.kds-ticket.state-new) {
  border-left-color: var(--kds-new);
}

:deep(.kds-ticket.state-preparing) {
  border-left-color: var(--kds-preparing);
}

:deep(.kds-ticket.state-ready) {
  border-left-color: var(--kds-ready);
}

:deep(.kds-ticket.state-served) {
  border-left-color: var(--kds-served);
}

:deep(.kds-ticket.state-voided) {
  border-left-color: var(--kds-void);
  opacity: 0.72;
}

:deep(.kds-ticket.urgency-over:not(.is-terminal)) {
  border-color: rgb(214 85 64 / 0.45);
  border-left-color: var(--kds-overdue);
  box-shadow: 0 0 0 1px rgb(214 85 64 / 0.16), 0 24px 56px rgb(214 85 64 / 0.13);
}

:deep(.kds-ticket-header) {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 14px 16px 10px;
}

:deep(.kds-ticket-pane) {
  display: grid;
  align-content: start;
  min-height: 72px;
}

:deep(.kds-ticket-pane.is-right) {
  text-align: right;
}

:deep(.kds-ticket-pane span),
:deep(.kds-items-head),
:deep(.kds-status-block > span) {
  color: var(--kds-fg3);
  font-size: 10px;
  font-weight: var(--kds-weight-label);
  letter-spacing: 0.22em;
  text-transform: uppercase;
}

:deep(.kds-ticket-pane strong) {
  color: var(--kds-fg0);
  font-family: var(--kds-font-s);
  font-size: 32px;
  font-weight: var(--kds-weight-hero);
  line-height: 1;
}

:deep(.kds-ticket-pane small) {
  color: var(--kds-fg2);
  font-family: var(--kds-font-m);
  font-size: 11px;
  font-weight: var(--kds-weight-caption);
  margin-top: 7px;
}

:deep(.kds-timer) {
  font-family: var(--kds-font-m) !important;
  font-variant-numeric: tabular-nums;
}

:deep(.urgency-warn .kds-timer) {
  color: var(--kds-warning);
}

:deep(.urgency-over .kds-timer) {
  color: var(--kds-overdue);
}

:deep(.is-struck) {
  text-decoration: line-through;
  text-decoration-thickness: 3px;
}

:deep(.kds-ticket-type-row) {
  gap: 8px;
  padding: 0 16px 10px;
}

:deep(.kds-pill),
:deep(.kds-recalled),
:deep(.kds-status-badge) {
  border-color: rgb(255 255 255 / 0.1);
  background: rgb(255 255 255 / 0.03);
  color: var(--kds-fg1);
  font-family: var(--kds-font-s);
  font-size: 12px;
  font-weight: var(--kds-weight-label);
  letter-spacing: 0.12em;
  padding: 4px 10px;
  text-transform: uppercase;
}

:deep(.kds-pill.is-refill) {
  border-color: rgb(92 182 176 / 0.32);
  background: rgb(92 182 176 / 0.18);
  color: #77d4cf;
}

:deep(.kds-recalled) {
  color: var(--kds-accent);
}

:deep(.kds-void-reason) {
  border-top: 1px solid rgb(255 255 255 / 0.06);
  border-bottom: 1px solid rgb(255 255 255 / 0.06);
  color: var(--kds-void);
  font-size: 13px;
  font-weight: var(--kds-weight-body);
  margin: 0;
  padding: 10px 16px;
}

:deep(.kds-items) {
  display: flex;
  min-height: 0;
  flex: 1;
  flex-direction: column;
}

:deep(.kds-items-head) {
  justify-content: space-between;
  padding: 0 16px 8px;
}

:deep(.kds-item-row) {
  width: 100%;
  min-height: 44px;
  gap: 10px;
  border: 0;
  border-top: 1px solid rgb(255 255 255 / 0.045);
  background: transparent;
  color: var(--kds-fg0);
  font-family: var(--kds-font-s);
  font-size: 16px;
  font-weight: var(--kds-weight-body);
  padding: 0 16px;
  text-align: left;
}

:deep(.density-compact .kds-item-row) {
  min-height: 44px;
  font-size: 15px;
}

:deep(.kds-item-row:not(:disabled):active) {
  background: rgb(246 181 109 / 0.08);
}

:deep(.kds-item-row:focus-visible) {
  outline: 3px solid rgb(246 181 109 / 0.45);
  outline-offset: -3px;
}

:deep(.kds-item-row.is-done) {
  color: rgb(244 239 231 / 0.62);
}

:deep(.kds-item-row.is-disabled) {
  cursor: not-allowed;
}

:deep(.kds-item-check) {
  width: 24px;
  height: 24px;
  border-color: rgb(255 255 255 / 0.22);
  border-radius: 6px;
}

:deep(.kds-item-qty) {
  color: var(--kds-accent);
  font-family: var(--kds-font-m);
  font-variant-numeric: tabular-nums;
  font-weight: var(--kds-weight-data);
  min-width: 34px;
}

:deep(.kds-item-name) {
  min-width: 0;
}

:deep(.kds-safety) {
  color: var(--kds-warning);
}

:deep(.kds-ticket-footer) {
  justify-content: space-between;
  gap: 12px;
  border-top: 1px solid rgb(255 255 255 / 0.06);
  padding: 12px 16px 14px;
}

:deep(.kds-status-block) {
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
}

:deep(.kds-status-badge) {
  gap: 6px;
  letter-spacing: 0.08em;
}

:deep(.kds-status-badge.state-new) {
  border-color: rgb(133 169 216 / 0.35);
  background: rgb(133 169 216 / 0.12);
  color: var(--kds-new);
}

:deep(.kds-status-badge.state-preparing) {
  border-color: rgb(222 138 72 / 0.35);
  background: rgb(222 138 72 / 0.12);
  color: var(--kds-preparing);
}

:deep(.kds-status-badge.state-ready) {
  border-color: rgb(111 199 120 / 0.35);
  background: rgb(111 199 120 / 0.12);
  color: var(--kds-ready);
}

:deep(.kds-status-badge.state-served) {
  color: var(--kds-served);
}

:deep(.kds-status-badge.state-voided) {
  color: var(--kds-void);
}

:deep(.kds-card-action) {
  min-width: 190px;
  min-height: 56px;
  color: #17100b;
  font-family: var(--kds-font-s);
  font-size: 14px;
  font-weight: var(--kds-weight-cta);
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

:deep(.kds-card-action:disabled),
:deep(.kds-card-action[aria-disabled="true"]) {
  opacity: 0.45;
}

:deep(.kds-card-action.is-recall) {
  border-color: rgb(246 181 109 / 0.28);
  background: rgb(246 181 109 / 0.08);
  color: var(--kds-accent);
}

:deep(.kds-card-action:focus-visible) {
  outline: 3px solid rgb(246 181 109 / 0.45);
  outline-offset: 2px;
}

:deep(.kds-empty) {
  display: grid;
  height: 100%;
  min-height: 0;
  place-items: center;
  align-content: center;
  gap: 10px;
  color: var(--kds-fg2);
  text-align: center;
}

:deep(.kds-empty svg) {
  width: 46px;
  height: 46px;
  color: var(--kds-ready);
}

:deep(.kds-empty strong) {
  color: var(--kds-fg0);
  font-family: var(--kds-font-d);
  font-size: 34px;
  font-weight: var(--kds-weight-display);
}

:deep(.kds-empty span) {
  font-size: 16px;
  font-weight: var(--kds-weight-body);
}

@media (prefers-reduced-motion: reduce) {
  *,
  :deep(*) {
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    scroll-behavior: auto !important;
  }
}
</style>
