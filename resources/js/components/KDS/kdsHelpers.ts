import type { KdsFilter, KdsThresholds, KdsTicket, KdsTicketState, KdsTicketType, KdsUrgency } from './kdsTypes'

export const ACTIVE_STATES: KdsTicketState[] = ['new', 'preparing']
export const TERMINAL_STATES: KdsTicketState[] = ['served', 'voided']
export const STAGE_SORT: Record<KdsTicketState, number> = {
  preparing: 0,
  ready: 0,
  new: 1,
  served: 2,
  voided: 3,
}

export const KDS_THRESHOLDS: KdsThresholds = {
  initial: {
    warn: 10 * 60,
    over: 14 * 60,
  },
  refill: {
    warn: 10 * 60,
    over: 14 * 60,
  },
}

export function isTerminal(state: KdsTicketState): boolean {
  return TERMINAL_STATES.includes(state)
}

export function isActive(state: KdsTicketState): boolean {
  return ACTIVE_STATES.includes(state)
}

export function elapsedFor(ticket: KdsTicket, now: number, clockOffset = 0): number {
  if (isTerminal(ticket.state)) {
    return ticket.frozenElapsed ?? ticket.elapsed
  }

  return Math.max(0, Math.floor((now + clockOffset - ticket.issuedAt) / 1000))
}

export function formatElapsed(seconds: number): string {
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60

  return `${mins}:${String(secs).padStart(2, '0')}`
}

export function urgencyFor(ticket: KdsTicket, now: number, clockOffset = 0, thresholds: KdsThresholds = KDS_THRESHOLDS): KdsUrgency {
  if (isTerminal(ticket.state)) {
    return 'ok'
  }

  const elapsed = elapsedFor(ticket, now, clockOffset)
  const threshold = thresholds[ticket.type]

  if (elapsed >= threshold.over) {
    return 'over'
  }

  if (elapsed >= threshold.warn) {
    return 'warn'
  }

  return 'ok'
}

export function filterTickets(tickets: KdsTicket[], filter: KdsFilter, now: number): KdsTicket[] {
  return tickets.filter((ticket) => {
    if (filter === 'active') {
      return isActive(ticket.state)
    }

    if (filter === 'overdue') {
      return urgencyFor(ticket, now) === 'over'
    }

    if (filter === 'preparing') {
      return ticket.state === 'preparing' || ticket.state === 'ready'
    }

    return ticket.state === filter
  })
}

export function sortTickets(tickets: KdsTicket[], now: number): KdsTicket[] {
  return [...tickets].sort((a, b) => {
    const urgencyA = urgencyFor(a, now) === 'over' ? 0 : 1
    const urgencyB = urgencyFor(b, now) === 'over' ? 0 : 1

    if (urgencyA !== urgencyB) {
      return urgencyA - urgencyB
    }

    const stageA = STAGE_SORT[a.state]
    const stageB = STAGE_SORT[b.state]

    if (stageA !== stageB) {
      return stageA - stageB
    }

    return elapsedFor(b, now) - elapsedFor(a, now)
  })
}

export function nextStateFor(state: KdsTicketState): KdsTicketState | null {
  if (state === 'new') {
    return 'preparing'
  }

  if (state === 'preparing' || state === 'ready') {
    return 'served'
  }

  return null
}

export function stateLabel(state: KdsTicketState): string {
  return {
    new: 'New',
    preparing: 'Preparing',
    ready: 'Preparing',
    served: 'Served',
    voided: 'Voided',
  }[state]
}

export function canAdvanceTicket(ticket: KdsTicket): boolean {
  if (ticket.state === 'preparing' || ticket.state === 'ready') {
    return ticket.items.every((item) => item.done === true)
  }

  return nextStateFor(ticket.state) !== null
}

/** Recall is the served→in_progress edge only; voided orders need a new ticket (see order-state.contract.md). */
export function canRecallTicket(ticket: KdsTicket): boolean {
  return ticket.state === 'served'
}

/** Primary action `:disabled` — Mark as Served gated until every checklist item is done. */
export function isAdvanceBlocked(ticket: KdsTicket): boolean {
  if (nextStateFor(ticket.state) === null) {
    return false
  }

  return !canAdvanceTicket(ticket)
}

export function applyAdvance(ticket: KdsTicket, now: number): KdsTicket {
  const next = nextStateFor(ticket.state)

  if (!next || !canAdvanceTicket(ticket)) {
    return ticket
  }

  if (next === 'served') {
    return {
      ...ticket,
      state: next,
      frozenElapsed: elapsedFor(ticket, now),
    }
  }

  return {
    ...ticket,
    state: next,
  }
}

export function ticketTypeLabel(type: KdsTicketType): string {
  return type === 'refill' ? 'Refill' : 'Initial Order'
}
