export type KdsTicketType = 'initial' | 'refill'
export type KdsTicketState = 'new' | 'preparing' | 'ready' | 'served' | 'voided'
export type KdsFilter = 'active' | 'overdue' | KdsTicketState
export type KdsUrgency = 'ok' | 'warn' | 'over'
export type KdsDensity = 'comfortable' | 'compact'

export interface KdsItem {
  id: string
  qty: number
  name: string
  done: boolean
  notes?: string
}

export interface KdsTicket {
  id: string
  table: string
  type: KdsTicketType
  issued: string
  issuedAt: number
  elapsed: number
  frozenElapsed?: number
  state: KdsTicketState
  items: KdsItem[]
  recalled?: number
  voidReason?: string
}

export interface KdsThreshold {
  warn: number
  over: number
}

export type KdsThresholds = Record<KdsTicketType, KdsThreshold>
