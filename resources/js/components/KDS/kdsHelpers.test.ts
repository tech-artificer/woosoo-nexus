import { describe, expect, it } from 'vitest'
import { applyAdvance, canAdvanceTicket, canRecallTicket, elapsedFor, filterTickets, isAdvanceBlocked, KDS_THRESHOLDS } from './kdsHelpers'
import type { KdsTicket, KdsTicketState } from './kdsTypes'

function preparingTicket(itemsDone: boolean[]): KdsTicket {
  return {
    id: 'K-1',
    table: 'T-1',
    type: 'initial',
    issued: '7:00 PM',
    issuedAt: Date.now(),
    elapsed: 60,
    state: 'preparing',
    items: itemsDone.map((done, index) => ({
      id: `item-${index}`,
      qty: 1,
      name: `Item ${index}`,
      done,
    })),
  }
}

function makeTicket(state: KdsTicketState, overrideIssuedAt?: number): KdsTicket {
  return {
    id: `K-${state}`,
    table: 'T-1',
    type: 'initial',
    issued: '7:00 PM',
    issuedAt: overrideIssuedAt ?? Date.now(),
    elapsed: 60,
    state,
    items: [],
  }
}

function ticketsWithStates(...states: KdsTicketState[]): KdsTicket[] {
  return states.map((state) => makeTicket(state))
}

describe('Mark as Served gating', () => {
  const now = Date.now()

  it('blocks advance when any preparing item is not done', () => {
    const ticket = preparingTicket([true, true, false])

    expect(canAdvanceTicket(ticket)).toBe(false)
    expect(isAdvanceBlocked(ticket)).toBe(true)
  })

  it('allows advance when every preparing item is done', () => {
    const ticket = preparingTicket([true, true, true])

    expect(canAdvanceTicket(ticket)).toBe(true)
    expect(isAdvanceBlocked(ticket)).toBe(false)
  })

  it('does not block new tickets without a checklist gate', () => {
    const ticket: KdsTicket = {
      ...preparingTicket([]),
      state: 'new',
      items: [{ id: 'a', qty: 1, name: 'Bulgogi', done: false }],
    }

    expect(canAdvanceTicket(ticket)).toBe(true)
    expect(isAdvanceBlocked(ticket)).toBe(false)
  })

  it('advances preparing to served when the gate passes', () => {
    const ticket = preparingTicket([true, true])

    expect(applyAdvance(ticket, now).state).toBe('served')
  })

  it('leaves preparing unchanged when the gate fails', () => {
    const ticket = preparingTicket([true, false])

    expect(applyAdvance(ticket, now).state).toBe('preparing')
  })
})

describe('Recall gating', () => {
  it('allows recall on served tickets only', () => {
    expect(canRecallTicket(makeTicket('served'))).toBe(true)
  })

  it.each<KdsTicketState>(['new', 'preparing', 'ready', 'voided'])('rejects recall from %s', (state) => {
    expect(canRecallTicket(makeTicket(state))).toBe(false)
  })

  it('blocks recall when recalled count reaches MAX_RECALLS', () => {
    const ticket = { ...makeTicket('served'), recalled: 5 }

    expect(canRecallTicket(ticket)).toBe(false)
  })

  it('allows recall when recalled count is below MAX_RECALLS', () => {
    const ticket = { ...makeTicket('served'), recalled: 4 }

    expect(canRecallTicket(ticket)).toBe(true)
  })
})

describe('filterTickets', () => {
  const now = Date.now()

  it('active filter returns new and preparing tickets only', () => {
    const tickets = ticketsWithStates('new', 'preparing', 'served', 'voided')

    expect(filterTickets(tickets, 'active', now).map((t) => t.id)).toEqual(['K-new', 'K-preparing'])
  })

  it('active filter returns empty array when all tickets are served or voided', () => {
    const tickets = ticketsWithStates('served', 'voided')

    expect(filterTickets(tickets, 'active', now)).toEqual([])
  })

  it('overdue filter returns only tickets past the over threshold', () => {
    const overdueNow = 2_000_000
    const overdueTicket = makeTicket('new', overdueNow - (KDS_THRESHOLDS.initial.over + 1) * 1000)
    const freshTicket = makeTicket('new', overdueNow - 60 * 1000)

    expect(filterTickets([overdueTicket, freshTicket], 'overdue', overdueNow)).toEqual([overdueTicket])
  })

  it('new filter returns only tickets with state new', () => {
    const tickets = ticketsWithStates('new', 'preparing', 'served', 'voided')

    expect(filterTickets(tickets, 'new', now)).toEqual([tickets[0]])
  })

  it('preparing filter returns tickets with state preparing or ready', () => {
    const tickets = ticketsWithStates('preparing', 'ready', 'new', 'served')

    expect(filterTickets(tickets, 'preparing', now).map((t) => t.id)).toEqual(['K-preparing', 'K-ready'])
  })

  it('served filter returns only served tickets', () => {
    const tickets = ticketsWithStates('served', 'new', 'preparing', 'voided')

    expect(filterTickets(tickets, 'served', now)).toEqual([tickets[0]])
  })

  it('voided filter returns only voided tickets', () => {
    const tickets = ticketsWithStates('voided', 'new', 'served', 'preparing')

    expect(filterTickets(tickets, 'voided', now)).toEqual([tickets[0]])
  })
})

describe('elapsedFor with clockOffset', () => {
  it('adds positive offset to live ticket elapsed', () => {
    const baseNow = 2_000_000_000_000
    const ticket = makeTicket('new', baseNow - 60_000)

    expect(elapsedFor(ticket, baseNow, 30_000)).toBe(90)
  })

  it('subtracts negative offset from live ticket elapsed, floored at 0', () => {
    const baseNow = 2_000_000_000_000
    const ticket = makeTicket('new', baseNow - 60_000)

    expect(elapsedFor(ticket, baseNow, -30_000)).toBe(30)
  })

  it('clockOffset = 0 matches no-offset behavior', () => {
    const baseNow = 2_000_000_000_000
    const ticket = makeTicket('new', baseNow - 60_000)

    expect(elapsedFor(ticket, baseNow, 0)).toBe(elapsedFor(ticket, baseNow))
  })

  it('terminal tickets ignore clockOffset', () => {
    const ticket: KdsTicket = {
      ...makeTicket('served'),
      frozenElapsed: 45,
    }
    const baseNow = 2_000_000_000_000

    expect(elapsedFor(ticket, baseNow, 99_999)).toBe(45)
    expect(elapsedFor(ticket, baseNow, -99_999)).toBe(45)
  })
})
