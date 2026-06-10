import { describe, expect, it } from 'vitest'
import { useKdsBoard } from './useKdsBoard'
import type { KdsTicket } from './kdsTypes'

function ticketWithItems(itemsDone: boolean[]): KdsTicket {
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

describe('useKdsBoard.applyItemToggle', () => {
  it('reconciles a matching item to done=true', () => {
    const board = useKdsBoard([ticketWithItems([false, false])])

    board.applyItemToggle({ order_id: 'K-1', item_id: 'item-0', done: true, done_at: '2026-06-09T12:00:00+00:00' })

    expect(board.tickets.value[0].items[0].done).toBe(true)
    expect(board.tickets.value[0].items[1].done).toBe(false)
  })

  it('reconciles a matching item to done=false', () => {
    const board = useKdsBoard([ticketWithItems([true, true])])

    board.applyItemToggle({ order_id: 'K-1', item_id: 'item-1', done: false, done_at: null })

    expect(board.tickets.value[0].items[1].done).toBe(false)
    expect(board.tickets.value[0].items[0].done).toBe(true)
  })

  it('is a no-op for an unknown order id', () => {
    const board = useKdsBoard([ticketWithItems([false])])

    board.applyItemToggle({ order_id: 'K-999', item_id: 'item-0', done: true, done_at: null })

    expect(board.tickets.value[0].items[0].done).toBe(false)
  })
})
