/** localStorage keys holding per-account data (precise-location weather and picks); cleared on sign-out or account change. */
export const USER_STORAGE_KEYS = [
  'watchwise:weather-mood:v1',
  'watchwise:weather-recommendations:v1',
  'watchwise:tonights-pick:v1',
]

export function clearUserStorage(storage: Pick<Storage, 'removeItem'> | undefined = typeof localStorage === 'undefined' ? undefined : localStorage) {
  for (const key of USER_STORAGE_KEYS) {
    try { storage?.removeItem(key) } catch { /* Storage may be unavailable. */ }
  }
}
