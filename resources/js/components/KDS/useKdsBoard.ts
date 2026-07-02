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
  device?: { name: string } | null
  package_name?: string | null
  guest_count?: number | null
  order_number?: string | null
  items?: Array<{
    id: string | number
    quantity: number
    name: string
    is_refill?: boolean
    done?: boolean
    done_at?: string | null
    notes?: string | null
    is_package_anchor?: boolean
  }>
  recalled?: number | null
  void_reason?: string | null
}

type ItemTogglePayload = {
  item_id: string | number
  order_id: string | number
  done: boolean
  done_at: string | null
}

const HIDDEN_STATUSES = new Set(['completed', 'cancelled', 'archived'])

function normalizeKdsState(state: string): KdsTicket['state'] {
  if (state === 'ready') {
    return 'preparing'
  }

  return state as KdsTicket['state']
}

function payloadToTicket(payload: OrderPayload): KdsTicket {
  const issuedAt = payload.created_at ? new Date(payload.created_at).getTime() : Date.now()
  const now = Date.now()
  const state = normalizeKdsState(payload.kds_state ?? 'new')
  const isTerminal = state === 'served' || state === 'voided'
  const elapsed = Math.max(0, Math.floor((now - issuedAt) / 1000))
  const frozenElapsed = isTerminal && payload.updated_at
    ? Math.max(0, Math.floor((new Date(payload.updated_at).getTime() - issuedAt) / 1000))
    : undefined

  return {
    id: String(payload.id),
    table: payload.table?.name ?? payload.device?.name ?? '—',
    type: (payload.kds_type ?? 'initial') as KdsTicket['type'],
    issued: payload.created_at
      ? new Intl.DateTimeFormat('en-US', { hour: 'numeric', minute: '2-digit' }).format(new Date(payload.created_at))
      : '',
    issuedAt,
    elapsed,
    frozenElapsed,
    state,
    items: (payload.items ?? [])
      .filter((it) => !it.is_package_anchor)
      .map((it) => ({
        id: String(it.id),
        qty: it.quantity ?? 1,
        name: it.name ?? '',
        done: (it.done ?? false),
        notes: it.notes ?? undefined,
      })),
    recalled: payload.recalled ?? undefined,
    voidReason: payload.void_reason ?? undefined,
    packageName: payload.package_name ?? undefined,
    guestCount: payload.guest_count ?? undefined,
    orderNumber: payload.order_number ?? undefined,
  }
}

export function useKdsBoard(initialTickets: KdsTicket[]) {
  const tickets = ref<KdsTicket[]>(initialTickets.map((t) => ({ ...t, items: t.items.map((i) => ({ ...i })) })))
  const clockOffset = ref(0)

  function setClockOffset(serverNow: number): void {
    clockOffset.value = serverNow - Date.now()
  }

  function replaceAll(newTickets: KdsTicket[]): void {
    tickets.value = newTickets.map((t) => ({ ...t, items: t.items.map((i) => ({ ...i })) }))
  }

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

  function applyItemToggle(payload: ItemTogglePayload): void {
    const orderId = String(payload.order_id)
    const itemId = String(payload.item_id)

    tickets.value = tickets.value.map((t) => {
      if (t.id !== orderId) {
        return t
      }

      return {
        ...t,
        items: t.items.map((it) =>
          it.id === itemId ? { ...it, done: payload.done } : it,
        ),
      }
    })
  }

  return { tickets, applyOrderUpdate, applyItemToggle, clockOffset, setClockOffset, replaceAll }
}
