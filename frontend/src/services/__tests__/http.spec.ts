import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { ApiError, http, onApiError } from '@/services/http'

type FetchArgs = [input: string, init?: RequestInit]

function jsonResponse(status: number, body?: unknown): Response {
  return new Response(body === undefined ? null : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  })
}

function clearCookies(): void {
  document.cookie.split('; ').forEach((cookie) => {
    const name = cookie.split('=')[0]
    if (name) document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`
  })
}

describe('http client', () => {
  let fetchMock: ReturnType<typeof vi.fn<(...args: FetchArgs) => Promise<Response>>>

  beforeEach(() => {
    clearCookies()
    fetchMock = vi.fn<(...args: FetchArgs) => Promise<Response>>()
    vi.stubGlobal('fetch', fetchMock)
  })

  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('sends cookies, JSON headers and the XSRF token, never an Authorization header', async () => {
    document.cookie = 'XSRF-TOKEN=abc%3D123; path=/'
    fetchMock.mockResolvedValue(jsonResponse(200, { data: { ok: true } }))

    await http.patch('/companies/1', { name: 'X' })

    const [url, init] = fetchMock.mock.calls[0]!
    const headers = init?.headers as Record<string, string>
    expect(url).toBe('/api/v1/companies/1')
    expect(init?.credentials).toBe('include')
    expect(headers['X-XSRF-TOKEN']).toBe('abc=123')
    expect(headers['Content-Type']).toBe('application/json')
    expect(headers.Authorization).toBeUndefined()
    expect(init?.body).toBe('{"name":"X"}')
  })

  it('fetches the CSRF cookie before the first write when it is missing', async () => {
    fetchMock.mockImplementation(async (url) => {
      if (url === '/sanctum/csrf-cookie') {
        document.cookie = 'XSRF-TOKEN=fresh; path=/'
        return new Response(null, { status: 204 })
      }
      return jsonResponse(200, {})
    })

    await http.post('/auth/login', {})

    expect(fetchMock.mock.calls.map((call) => call[0])).toEqual(['/sanctum/csrf-cookie', '/api/v1/auth/login'])
    expect((fetchMock.mock.calls[1]![1]?.headers as Record<string, string>)['X-XSRF-TOKEN']).toBe('fresh')
  })

  it('turns 422 responses into an ApiError with field errors', async () => {
    fetchMock.mockResolvedValue(
      jsonResponse(422, { message: 'O campo nome é obrigatório.', errors: { name: ['O campo nome é obrigatório.'] } }),
    )

    const error = await http.get('/x').catch((e: unknown) => e)

    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).status).toBe(422)
    expect((error as ApiError).isValidation).toBe(true)
    expect((error as ApiError).field('name')).toBe('O campo nome é obrigatório.')
  })

  it('retries once after refreshing the CSRF cookie on 419', async () => {
    document.cookie = 'XSRF-TOKEN=stale; path=/'
    fetchMock
      .mockResolvedValueOnce(jsonResponse(419, { message: 'CSRF token mismatch.' }))
      .mockResolvedValueOnce(new Response(null, { status: 204 })) // csrf-cookie
      .mockResolvedValueOnce(jsonResponse(200, { data: 1 }))

    await expect(http.post('/x')).resolves.toEqual({ data: 1 })
    expect(fetchMock).toHaveBeenCalledTimes(3)
  })

  it('does not retry 419 more than once', async () => {
    document.cookie = 'XSRF-TOKEN=stale; path=/'
    fetchMock.mockImplementation(async (url) =>
      url === '/sanctum/csrf-cookie' ? new Response(null, { status: 204 }) : jsonResponse(419, {}),
    )

    await expect(http.post('/x')).rejects.toMatchObject({ status: 419 })
    expect(fetchMock).toHaveBeenCalledTimes(3)
  })

  it('notifies listeners and hides server error details on 5xx', async () => {
    const listener = vi.fn()
    const stop = onApiError(listener)
    fetchMock.mockResolvedValue(jsonResponse(500, { message: 'SQLSTATE[08006] connection refused' }))

    const error = (await http.get('/x').catch((e: unknown) => e)) as ApiError
    stop()

    expect(error.message).toBe('Ocorreu um erro inesperado. Tente novamente em instantes.')
    expect(listener).toHaveBeenCalledWith(error, { method: 'GET', path: '/x' })
  })

  it('reports network failures as status 0', async () => {
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'))

    await expect(http.get('/x')).rejects.toMatchObject({ status: 0 })
  })

  it('serializes query strings, skipping empty values and expanding arrays', async () => {
    fetchMock.mockResolvedValue(jsonResponse(200, {}))

    await http.get('/appointments', { from: '2026-11-16', search: '', status: ['scheduled', 'confirmed'], page: null })

    expect(fetchMock.mock.calls[0]![0]).toBe(
      '/api/v1/appointments?from=2026-11-16&status%5B%5D=scheduled&status%5B%5D=confirmed',
    )
  })
})
