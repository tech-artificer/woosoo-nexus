import { onBeforeUnmount, onMounted, ref } from 'vue'
import type { useKdsBoard } from './useKdsBoard'

type Board = ReturnType<typeof useKdsBoard>

type EchoOptions = {
  onOrderCreated?: () => void
}

export function useKdsEcho(board: Board, options: EchoOptions = {}) {
  let channel: ReturnType<typeof window.Echo.channel> | null = null
  let pusherConnection: any = null
  // Reactive WebSocket connectivity for the Online/Offline badge. Starts true so the
  // badge doesn't flash Offline before the first connection event lands.
  const connected = ref(true)

  function handleOrderCreated(e: unknown): void {
    // Play the new-order chime unconditionally — `.order.created` is a true new-order
    // signal and F1 removed the duplicate broadcasts, so no dedup wrapper is needed.
    options.onOrderCreated?.()
    handleOrderEvent(e)
  }

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
    // Guard the full shape applyItemToggle relies on — a malformed event must not
    // corrupt ticket state with an undefined order or non-boolean done flag.
    if (!payload.item_id || !payload.order_id || typeof payload.done !== 'boolean') {
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
      .listen('.order.created', handleOrderCreated)
      .listen('.order.updated', handleOrderEvent)
      .listen('.order.details.updated', handleOrderEvent)
      .listen('.order.voided', handleOrderEvent)
      .listen('.order.completed', handleOrderEvent)
      .listen('.order.cancelled', handleOrderEvent)
      .listen('.item.toggled', handleItemToggle)

    // Coupling note: `connector.pusher.connection` is specific to the pusher-js client
    // that Laravel Reverb uses. If the broadcaster/driver is ever swapped, this binding
    // must be revisited — the connection state shape differs across Echo connectors.
    const pusher = (window.Echo as any)?.connector?.pusher
    if (pusher?.connection) {
      pusherConnection = pusher.connection
      connected.value = pusherConnection.state === 'connected'
      pusherConnection.bind('connected', () => {
        connected.value = true
      })
      pusherConnection.bind('disconnected', () => {
        connected.value = false
      })
      pusherConnection.bind('unavailable', () => {
        connected.value = false
      })
    }
  })

  onBeforeUnmount(() => {
    if (pusherConnection) {
      pusherConnection.unbind('connected')
      pusherConnection.unbind('disconnected')
      pusherConnection.unbind('unavailable')
      pusherConnection = null
    }
    if (channel && typeof (window.Echo as any).leave === 'function') {
      ;(window.Echo as any).leave('admin.orders')
      channel = null
    }
  })

  return { connected }
}
