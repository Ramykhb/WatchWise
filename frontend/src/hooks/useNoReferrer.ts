import { useEffect } from 'react'

/** The address of these pages carries a secret or signed link, so no request they make may reveal it in a Referer header. */
export function useNoReferrer() {
  useEffect(() => {
    const meta = document.createElement('meta')
    meta.name = 'referrer'
    meta.content = 'no-referrer'
    document.head.appendChild(meta)
    return () => { meta.remove() }
  }, [])
}
