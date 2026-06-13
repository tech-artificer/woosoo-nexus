import { onBeforeUnmount, onMounted } from 'vue'
import type { useKdsBoard } from './useKdsBoard'

type Board = ReturnType<typeof useKdsBoard>

export function useKdsEcho(board: Board) {
  let channel: ReturnType<typeof window.Echo.channel> | null = null

  function handleOrderEvent(e: unknown): void {
    if (!e || typeof e !== 'object') {
      return
    }
    const payload = (e as Record<string, unknown>).order ?? e
    if (!payload || typeof payload !== 'object' || !(payload as Record<string, unknown>).id) {
      return
    }
    board.applyOrderUpdate(payload as Parameters<typeof board.applyOrderUpdate>[0])
  }

  function handleItemToggle(e: unknown): void {
    if (!e || typeof e !== 'object') {
      return
    }
    const payload = e as Record<string, unknown>
    if (!payload.item_id) {
      return
    }
    board.applyItemToggle(payload as Parameters<typeof board.applyItemToggle>[0])
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
      .listen('.order.archived', handleOrderEvent)
      .listen('.item.toggled', handleItemToggle)
  })

  onBeforeUnmount(() => {
    if (channel && typeof (window.Echo as any).leave === 'function') {
      ;(window.Echo as any).leave('admin.orders')
      channel = null
    }
  })
}
