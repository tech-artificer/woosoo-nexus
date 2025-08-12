export enum OrderStatus {
    Pending = 'pending',
    Confirmed = 'confirmed',
    Completed = 'completed',
    Voided = 'voided',
    Archived = 'archived',
}

export function getOrderStatusLabel(status: OrderStatus): string {
    switch (status) {
        case OrderStatus.Pending:
            return 'Pending Confirmation';
        case OrderStatus.Confirmed:
            return 'Order Confirmed';
        case OrderStatus.Completed:
            return 'Order Completed';
        // case OrderStatus.Archived:
        //     return 'Order Archived';
        case OrderStatus.Voided:
            return 'Order Voided';
        default:
            return status; // Fallback in case of unknown status
    }
}

export function getNextOrderStatus(currentStatus: OrderStatus): OrderStatus | null {
  const statusFlow: Record<OrderStatus, OrderStatus | null> = {
    [OrderStatus.Pending]: OrderStatus.Confirmed,
    [OrderStatus.Confirmed]: OrderStatus.Completed,
    [OrderStatus.Confirmed]: OrderStatus.Voided,
    [OrderStatus.Completed]: OrderStatus.Archived,
    [OrderStatus.Archived]: null, // No next status after completed
    [OrderStatus.Voided]: null, // No next status after voided
  };

  return statusFlow[currentStatus] || null;
}

export enum TableStatus {
    OPEN= 'OPEN',
    AVAILABLE = 'AVAILABLE',
    LOCKED = 'LOCKED',
    DIRTY = 'DIRTY',
}