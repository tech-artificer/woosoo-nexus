import { onBeforeUnmount, onMounted } from 'vue'
import type { useKdsBoard } from './useKdsBoard'

type Board = ReturnType<typeof useKdsBoard>

export function useKdsEcho(board: Board) {
  let channel: ReturnType<typeof window.Echo.channel> | null = null

  function handleOrderEvent(e: unknown): void {
    const payload = (e as any)?.order ?? e
    if (!payload?.id) {
      return
    }
    board.applyOrderUpdate(payload)
  }

  function handleItemToggle(e: unknown): void {
    const payload = e as any
    if (!payload?.item_id) {
      return
    }
    board.applyItemToggle(payload)
  }

  onMounted(() => {
    if (!window.Echo) {
      console.warn('[useKdsEcho] Echo not available')
      return
    }

    channel = window.Echo.channel('admin.orders')
    channel
      .listen('.order.created', handleOrderEvent)
      .listen('.order.updated', handleOrderEvent)
      .listen('.order.voided', handleOrderEvent)
      .listen('.order.completed', handleOrderEvent)
      .listen('.order.cancelled', handleOrderEvent)
      .listen('.item.toggled', handleItemToggle)
  })

  onBeforeUnmount(() => {
    if (channel && typeof (window.Echo as any).leave === 'function') {
      ;(window.Echo as any).leave('admin.orders')
      channel = null
    }
  })
}
