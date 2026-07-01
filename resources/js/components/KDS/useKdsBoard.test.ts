import { describe, expect, it } from 'vitest'
import { useKdsBoard } from './useKdsBoard'
import type { KdsTicket, KdsTicketState } from './kdsTypes'

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

function makeOrderPayload(id: string, status: string, kdsState?: string) {
  return {
    id,
    status,
    kds_state: kdsState ?? 'new',
    table: { name: 'T-1' },
    items: [{ id: 'item-0', quantity: 1, name: 'Bulgogi' }],
  }
}

function boardWithTicket(state: KdsTicketState = 'preparing') {
  const board = useKdsBoard([ticketWithItems([false])])
  board.tickets.value[0].state = state
  return board
}

describe('useKdsBoard.applyItemToggle', () => {
  it('reconciles a matching item to done=true', () => {
    const board = useKdsBoard([ticketWithItems([false, false])])

    board.applyItemToggle({
      order_id: 'K-1',
      item_id: 'item-0',
      done: true,
      done_at: '2026-06-09T12:00:00+00:00',
    })

    expect(board.tickets.value[0].items[0].done).toBe(true)
    expect(board.tickets.value[0].items[1].done).toBe(false)
  })

  it('reconciles a matching item to done=false', () => {
    const board = useKdsBoard([ticketWithItems([true, true])])

    board.applyItemToggle({
      order_id: 'K-1',
      item_id: 'item-1',
      done: false,
      done_at: null,
    })

    expect(board.tickets.value[0].items[1].done).toBe(false)
    expect(board.tickets.value[0].items[0].done).toBe(true)
  })

  it('is a no-op for an unknown order id', () => {
    const board = useKdsBoard([ticketWithItems([false])])

    board.applyItemToggle({
      order_id: 'K-999',
      item_id: 'item-0',
      done: true,
      done_at: null,
    })

    expect(board.tickets.value[0].items[0].done).toBe(false)
  })
})

describe('useKdsBoard.applyOrderUpdate', () => {
  it('adds a new ticket when the order id is not on the board', () => {
    const board = useKdsBoard([])

    board.applyOrderUpdate(makeOrderPayload('K-2', 'confirmed'))

    expect(board.tickets.value).toHaveLength(1)
    expect(board.tickets.value[0].id).toBe('K-2')
  })

  it('replaces an existing ticket when the order id is already on the board', () => {
    const board = boardWithTicket('new')

    board.applyOrderUpdate(makeOrderPayload('K-1', 'confirmed', 'preparing'))

    expect(board.tickets.value).toHaveLength(1)
    expect(board.tickets.value[0].state).toBe('preparing')
  })

  it('removes a ticket when the status is in the hidden set (completed | cancelled | archived)', () => {
    const board = boardWithTicket()

    board.applyOrderUpdate(makeOrderPayload('K-1', 'completed'))

    expect(board.tickets.value).toHaveLength(0)
  })

  it('board tickets array is empty after the last ticket is removed via hidden status', () => {
    const board = boardWithTicket()

    board.applyOrderUpdate(makeOrderPayload('K-1', 'cancelled'))

    expect(board.tickets.value).toEqual([])
  })

  it('uses device name as fallback when table is null', () => {
    const board = useKdsBoard([])
    const payload = {
      id: 'K-3',
      status: 'confirmed',
      table: null,
      device: { name: 'POS-3' },
      items: [],
    }

    board.applyOrderUpdate(payload)

    expect(board.tickets.value[0].table).toBe('POS-3')
  })

  it('falls back to em-dash when both table and device are null', () => {
    const board = useKdsBoard([])
    const payload = {
      id: 'K-4',
      status: 'confirmed',
      table: null,
      device: null,
      items: [],
    }

    board.applyOrderUpdate(payload)

    expect(board.tickets.value[0].table).toBe('—')
  })

  it('prefers table name over device name', () => {
    const board = useKdsBoard([])
    const payload = {
      id: 'K-5',
      status: 'confirmed',
      table: { name: 'T-1' },
      device: { name: 'POS-5' },
      items: [],
    }

    board.applyOrderUpdate(payload)

    expect(board.tickets.value[0].table).toBe('T-1')
  })

  it('excludes the package-anchor item and surfaces packageName/guestCount', () => {
    const board = useKdsBoard([])
    const payload = {
      id: 'K-6',
      status: 'confirmed',
      table: { name: 'T-6' },
      package_name: 'Classic Feast',
      guest_count: 4,
      items: [
        { id: 1, quantity: 4, name: 'Classic Feast', is_package_anchor: true },
        { id: 2, quantity: 1, name: 'Plain Samgyupsal', is_package_anchor: false },
      ],
    }

    board.applyOrderUpdate(payload)

    expect(board.tickets.value[0].items).toHaveLength(1)
    expect(board.tickets.value[0].items[0].name).toBe('Plain Samgyupsal')
    expect(board.tickets.value[0].packageName).toBe('Classic Feast')
    expect(board.tickets.value[0].guestCount).toBe(4)
  })
})
