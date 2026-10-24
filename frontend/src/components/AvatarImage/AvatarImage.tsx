import { useState, type ReactNode } from 'react'

type AvatarImageProps = {
  src: string | null | undefined
  /** Shown when there is no photo or it fails to load, e.g. the user's initials. */
  fallback: ReactNode
  className?: string
  alt?: string
}

/**
 * A profile photo that falls back to initials when it is missing or doesn't load (Google's
 * image server often refuses hot-linked photos). Sent without a referrer, as Google expects.
 */
export default function AvatarImage({ src, fallback, className, alt = '' }: AvatarImageProps) {
  const [failed, setFailed] = useState<string | null>(null)
  if (!src || failed === src) return <>{fallback}</>
  return <img className={className} src={src} alt={alt} referrerPolicy="no-referrer" onError={() => setFailed(src)} />
}
