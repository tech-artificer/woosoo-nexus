export interface PosTerminal {
    id: string
    name: string
    type: string
    ip_address: string | null
    port: number | null
    is_active: number | boolean
    terminal_session_id: string | null
    session_id: string | null
    terminal_session_opened_at: string | null
    terminal_session_closed_at: string | null
    session_closed_at: string | null
    open_orders_count: number | string
}

export interface PosTable {
    id: string
    name: string
    status: string
    is_available: number | boolean
    is_locked: number | boolean
    table_group_id: string
    order_created_in: string | null
    open_orders_count: number | string
    is_occupied: number | boolean
}

export interface PosOrder {
    id: string
    reference: string
    date_time_opened: string
    guest_count: number | string
    terminal_id: string
    total_amount: number | string
    paid_amount: number | string
    is_settled: number | boolean
    resetable_transaction_number: string | null
    table_names: string | null
}
