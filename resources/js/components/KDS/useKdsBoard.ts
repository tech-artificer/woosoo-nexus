import { ref } from 'vue'
import type { KdsTicket } from './kdsTypes'

type OrderPayload = {
  id: string | number
  status: string
  kds_state?: string
  kds_type?: string
  created_at?: string
  updated_at?: string
  table?: { name: string } | null
  items?: Array<{
    id: string | number
    quantity: number
    name: string
    is_refill?: boolean
  }>
  void_reason?: string
}

const HIDDEN_STATUSES = new Set(['completed', 'cancelled', 'archived'])

function payloadToTicket(payload: OrderPayload): KdsTicket {
  const issuedAt = payload.created_at ? new Date(payload.created_at).getTime() : Date.now()
  const now = Date.now()
  const state = (payload.kds_state ?? 'new') as KdsTicket['state']
  const isTerminal = state === 'served' || state === 'voided'
  const elapsed = Math.max(0, Math.floor((now - issuedAt) / 1000))
  const frozenElapsed = isTerminal && payload.updated_at
    ? Math.max(0, Math.floor((new Date(payload.updated_at).getTime() - issuedAt) / 1000))
    : undefined

  return {
    id: String(payload.id),
    table: payload.table?.name ?? '—',
    type: (payload.kds_type ?? 'initial') as KdsTicket['type'],
    issued: payload.created_at
      ? new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(new Date(payload.created_at))
      : '',
    issuedAt,
    elapsed,
    frozenElapsed,
    state,
    items: (payload.items ?? []).map((it) => ({
      id: String(it.id),
      qty: it.quantity ?? 1,
      name: it.name ?? '',
      done: false,
    })),
    recalled: undefined,
    voidReason: payload.void_reason,
  }
}

export function useKdsBoard(initialTickets: KdsTicket[]) {
  const tickets = ref<KdsTicket[]>(initialTickets.map((t) => ({ ...t, items: t.items.map((i) => ({ ...i })) })))

  function applyOrderUpdate(payload: OrderPayload): void {
    const id = String(payload.id)

    if (HIDDEN_STATUSES.has(payload.status)) {
      tickets.value = tickets.value.filter((t) => t.id !== id)
      return
    }

    const ticket = payloadToTicket(payload)
    const idx = tickets.value.findIndex((t) => t.id === id)

    if (idx >= 0) {
      tickets.value = tickets.value.map((t, i) => (i === idx ? ticket : t))
    } else {
      tickets.value = [...tickets.value, ticket]
    }
  }

  return { tickets, applyOrderUpdate }
}
