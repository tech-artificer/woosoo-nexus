export interface WebListResponse<T> {
    data: T[]
    meta: {
        page: number
        perPage: number
        total: number
        lastPage: number
        sortBy?: string | null
        sortDir?: 'asc' | 'desc' | null
    }
}