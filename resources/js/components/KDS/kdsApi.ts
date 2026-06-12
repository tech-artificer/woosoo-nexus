function csrfToken(): string {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
}

async function parseError(response: Response): Promise<string> {
  try {
    const body = await response.json()
    if (typeof body?.message === 'string') {
      return body.message
    }
  } catch {
    // Fall through to generic message.
  }

  return 'Something went wrong. Please try again.'
}

export type KdsActionResponse = {
  status: string
  order: Record<string, unknown>
}

export type KdsToggleResponse = {
  item_id: string | number
  order_id: string | number
  done: boolean
  done_at: string | null
}

export async function postKdsAdvance(orderId: string): Promise<KdsActionResponse> {
  const response = await fetch(route('kds.advance', orderId), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (!response.ok) {
    throw new Error(await parseError(response))
  }

  return response.json()
}

export async function postKdsRecall(orderId: string): Promise<KdsActionResponse> {
  const response = await fetch(route('kds.orders.recall', orderId), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (!response.ok) {
    throw new Error(await parseError(response))
  }

  return response.json()
}

export async function postKdsToggleItem(itemId: string): Promise<KdsToggleResponse> {
  const response = await fetch(route('kds.toggle-item', itemId), {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    },
  })

  if (!response.ok) {
    throw new Error(await parseError(response))
  }

  return response.json()
}
