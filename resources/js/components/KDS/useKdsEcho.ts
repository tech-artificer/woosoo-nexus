import { onBeforeUnmount, onMounted } from 'vue'
import type { useKdsBoard } from './useKdsBoard'

type Board = ReturnType<typeof useKdsBoard>

export function useKdsEcho(board: Board) {
  let channel: ReturnType<typeof window.Echo.channel> | null = null

  function handleEvent(e: unknown): void {
    const payload = (e as any)?.order ?? e
    if (!payload?.id) {
      return
    }
    board.applyOrderUpdate(payload)
  }

  onMounted(() => {
    if (!window.Echo) {
      console.warn('[useKdsEcho] Echo not available')
      return
    }

    channel = window.Echo.channel('admin.orders')
    channel
      .listen('.order.created', handleEvent)
      .listen('.order.updated', handleEvent)
      .listen('.order.voided', handleEvent)
      .listen('.order.completed', handleEvent)
      .listen('.order.cancelled', handleEvent)
  })

  onBeforeUnmount(() => {
    if (channel && typeof (window.Echo as any).leave === 'function') {
      ;(window.Echo as any).leave('admin.orders')
      channel = null
    }
  })
}
