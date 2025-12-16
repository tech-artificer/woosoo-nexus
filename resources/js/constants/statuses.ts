export const ORDER_STATUS = {
  PENDING: 'pending',
  CONFIRMED: 'confirmed',
  IN_PROGRESS: 'in_progress',
  READY: 'ready',
  SERVED: 'served',
  COMPLETED: 'completed',
  CANCELLED: 'cancelled',
  VOIDED: 'voided',
  ARCHIVED: 'archived',
} as const;

export type OrderStatus = typeof ORDER_STATUS[keyof typeof ORDER_STATUS];

export const ITEM_STATUS = {
  PENDING: 'pending',
  PREPARING: 'preparing',
  READY: 'ready',
  SERVED: 'served',
  CANCELLED: 'cancelled',
  VOIDED: 'voided',
  RETURNED: 'returned',
} as const;

export type ItemStatus = typeof ITEM_STATUS[keyof typeof ITEM_STATUS];

export const ORDER_STATUS_VALUES = Object.values(ORDER_STATUS) as OrderStatus[];
export const ITEM_STATUS_VALUES = Object.values(ITEM_STATUS) as ItemStatus[];

export default { ORDER_STATUS, ITEM_STATUS, ORDER_STATUS_VALUES, ITEM_STATUS_VALUES };
