export interface ApiResponse<T> {
  data: T
}

export interface PaginatedResponse<T> {
  data: T[]
  meta: PaginationMeta
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiError {
  message: string
  errors?: Record<string, string[]>
}

export interface ListParams {
  page?: number
  limit?: number | 'all'
  orderByField?: string
  orderBy?: 'asc' | 'desc'
  search?: string
}

export interface DateRangeParams {
  from_date?: string
  to_date?: string
}

export interface NextNumberResponse {
  nextNumber: string
}

export interface DeletePayload {
  ids: number[]
}
