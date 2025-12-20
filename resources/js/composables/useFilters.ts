import { ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { ORDER_STATUS_VALUES } from '@/constants/statuses'

function debounce<T extends (...args: any[]) => void>(fn: T, ms = 300) {
  let t: number | undefined
  return (...args: Parameters<T>) => {
    if (t) window.clearTimeout(t)
    t = window.setTimeout(() => fn(...args), ms)
  }
}

export interface FiltersState {
  status: string[]
  search: string
  date_from?: string
  date_to?: string
}

export function useFilters(initial: Partial<{
  status?: string | string[]
  search?: string
  date_from?: string
  date_to?: string
}> = {}) {
  const resolveStatus = (): string[] => {
    if (Array.isArray(initial.status)) return initial.status
    if (typeof initial.status === 'string') {
      return initial.status.split(',').map((s: string) => s.trim()).filter(Boolean)
    }
    return []
  }
  
  const status = ref<string[]>(resolveStatus())
  const search = ref<string>(String(initial.search ?? ''))
  const date_from = ref<string | undefined>(initial.date_from)
  const date_to = ref<string | undefined>(initial.date_to)

  const applyFilters = (extra: Record<string, any> = {}) => {
    const q: Record<string, any> = {
      ...(status.value.length ? { status: status.value.join(',') } : {}),
      ...(search.value ? { search: search.value } : {}),
      ...(date_from.value ? { date_from: date_from.value } : {}),
      ...(date_to.value ? { date_to: date_to.value } : {}),
      ...extra,
    }
    router.visit(route('orders.index'), { data: q, preserveState: true })
  }

  const clearFilters = () => {
    status.value = []
    search.value = ''
    date_from.value = undefined
    date_to.value = undefined
    router.visit(route('orders.index'))
  }

  const debouncedApplySearch = debounce(() => applyFilters())

  watch(search, () => debouncedApplySearch())

  return {
    status,
    search,
    date_from,
    date_to,
    applyFilters,
    clearFilters,
    ORDER_STATUS_VALUES,
  }
}
