/**
 * The page a guest was sent from, as a path + search + hash. Only an internal location from router state
 * is accepted (never a URL or protocol-relative path), so it cannot become an open redirect.
 */
export function returnToFrom(state: unknown, fallback = '/home-page'): string {
  const from = (state as { from?: { pathname?: unknown; search?: unknown; hash?: unknown } } | null | undefined)?.from
  const pathname = from?.pathname
  if (typeof pathname !== 'string' || !pathname.startsWith('/') || pathname.startsWith('//') || pathname.includes('\\')) return fallback
  const search = typeof from?.search === 'string' && (from.search === '' || from.search.startsWith('?')) ? from.search : ''
  const hash = typeof from?.hash === 'string' && (from.hash === '' || from.hash.startsWith('#')) ? from.hash : ''
  return pathname + search + hash
}
