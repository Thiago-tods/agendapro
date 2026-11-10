/**
 * HTTP client for the AgendaPro API.
 *
 * Authentication is Sanctum SPA (stateful) auth: an HttpOnly session cookie plus the
 * XSRF-TOKEN cookie, which is echoed back in the X-XSRF-TOKEN header on every request.
 * No token is ever stored by this code (no localStorage, no Authorization header).
 */

const API_PREFIX = '/api/v1'
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie'
const XSRF_COOKIE = 'XSRF-TOKEN'

export type FieldErrors = Record<string, string[]>

const FALLBACK_MESSAGES: Record<number, string> = {
  0: 'Não foi possível conectar ao servidor. Verifique sua conexão e tente novamente.',
  401: 'Sua sessão terminou. Entre novamente.',
  403: 'Você não tem permissão para realizar esta ação.',
  404: 'Recurso não encontrado.',
  409: 'A operação conflita com dados existentes.',
  419: 'Sua sessão expirou. Recarregue a página e tente novamente.',
  422: 'Verifique os dados informados.',
  429: 'Muitas tentativas. Aguarde um momento e tente novamente.',
  500: 'Ocorreu um erro inesperado. Tente novamente em instantes.',
}

/** Normalized error for every failed API call. */
export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: FieldErrors

  constructor(status: number, message: string, fieldErrors: FieldErrors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }

  get isValidation(): boolean {
    return this.status === 422 && Object.keys(this.fieldErrors).length > 0
  }

  /** First message for a field, ready to show under an input. */
  field(name: string): string | undefined {
    return this.fieldErrors[name]?.[0]
  }
}

export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError
}

type ErrorListener = (error: ApiError, request: { method: string; path: string }) => void
const listeners = new Set<ErrorListener>()

/** Central hook for cross-cutting reactions (e.g. a 401 ends the local session). */
export function onApiError(listener: ErrorListener): () => void {
  listeners.add(listener)
  return () => listeners.delete(listener)
}

type Query = Record<string, string | number | boolean | null | undefined | Array<string | number>>

interface RequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: unknown
  query?: Query
  signal?: AbortSignal
}

function readCookie(name: string): string | null {
  const match = document.cookie.split('; ').find((part) => part.startsWith(`${name}=`))
  return match ? decodeURIComponent(match.slice(name.length + 1)) : null
}

let csrfRequest: Promise<void> | null = null

/** Fetches a fresh XSRF-TOKEN cookie. Concurrent callers share one request. */
export function refreshCsrfCookie(): Promise<void> {
  csrfRequest ??= fetch(CSRF_COOKIE_URL, { credentials: 'include', headers: { Accept: 'application/json' } })
    .then(() => undefined)
    .finally(() => {
      csrfRequest = null
    })
  return csrfRequest
}

function buildUrl(path: string, query?: Query): string {
  const params = new URLSearchParams()
  for (const [key, value] of Object.entries(query ?? {})) {
    if (value === null || value === undefined || value === '') continue
    if (Array.isArray(value)) value.forEach((item) => params.append(`${key}[]`, String(item)))
    else params.append(key, String(value))
  }
  const search = params.toString()
  return `${API_PREFIX}${path}${search ? `?${search}` : ''}`
}

async function parseBody(response: Response): Promise<unknown> {
  if (response.status === 204) return undefined
  const text = await response.text()
  if (!text) return undefined
  try {
    return JSON.parse(text) as unknown
  } catch {
    return undefined
  }
}

function toApiError(status: number, body: unknown): ApiError {
  const payload = (typeof body === 'object' && body !== null ? body : {}) as {
    message?: unknown
    errors?: unknown
  }
  const fieldErrors = (typeof payload.errors === 'object' && payload.errors !== null ? payload.errors : {}) as FieldErrors
  const fallback = FALLBACK_MESSAGES[status] ?? FALLBACK_MESSAGES[status >= 500 ? 500 : 0] ?? ''
  const message = typeof payload.message === 'string' && payload.message && status < 500 ? payload.message : fallback
  return new ApiError(status, message, fieldErrors)
}

async function send<T>(path: string, options: RequestOptions, retried: boolean): Promise<T> {
  const method = options.method ?? 'GET'
  const isWrite = method !== 'GET'

  if (isWrite && !readCookie(XSRF_COOKIE)) {
    await refreshCsrfCookie()
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const xsrf = readCookie(XSRF_COOKIE)
  if (xsrf) headers['X-XSRF-TOKEN'] = xsrf
  if (options.body !== undefined) headers['Content-Type'] = 'application/json'

  let response: Response
  try {
    response = await fetch(buildUrl(path, options.query), {
      method,
      headers,
      credentials: 'include',
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal: options.signal,
    })
  } catch (cause) {
    if (cause instanceof DOMException && cause.name === 'AbortError') throw cause
    const error = new ApiError(0, FALLBACK_MESSAGES[0] ?? '')
    listeners.forEach((listener) => listener(error, { method, path }))
    throw error
  }

  // CSRF token rotated (e.g. after login/logout in another tab): refresh once and retry.
  if (response.status === 419 && !retried) {
    await refreshCsrfCookie()
    return send<T>(path, options, true)
  }

  const body = await parseBody(response)

  if (!response.ok) {
    const error = toApiError(response.status, body)
    listeners.forEach((listener) => listener(error, { method, path }))
    throw error
  }

  return body as T
}

export const http = {
  get: <T>(path: string, query?: Query, signal?: AbortSignal) => send<T>(path, { method: 'GET', query, signal }, false),
  post: <T>(path: string, body?: unknown) => send<T>(path, { method: 'POST', body }, false),
  put: <T>(path: string, body?: unknown) => send<T>(path, { method: 'PUT', body }, false),
  patch: <T>(path: string, body?: unknown) => send<T>(path, { method: 'PATCH', body }, false),
  delete: <T = void>(path: string) => send<T>(path, { method: 'DELETE' }, false),
}
