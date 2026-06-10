import { describe, expect, it } from 'vitest'
import { applyAdvance, canAdvanceTicket, isAdvanceBlocked } from './kdsHelpers'
import type { KdsTicket } from './kdsTypes'

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

describe('Mark as Served gating', () => {
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
    const now = Date.now()

    expect(applyAdvance(ticket, now).state).toBe('served')
  })

  it('leaves preparing unchanged when the gate fails', () => {
    const ticket = preparingTicket([true, false])
    const now = Date.now()

    expect(applyAdvance(ticket, now).state).toBe('preparing')
  })
})
