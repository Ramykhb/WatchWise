import styles from './Toggle.module.css'

type ToggleProps = {
  checked: boolean
  onChange: (checked: boolean) => void
  /** Id of the element that names the setting (e.g. the row's title). */
  labelledBy?: string
  label?: string
  className?: string
}

/** 48×28 on/off switch (Figma "Toggle": mint when on, grey when off). */
export default function Toggle({ checked, onChange, labelledBy, label, className }: ToggleProps) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-labelledby={labelledBy}
      aria-label={labelledBy ? undefined : label}
      className={className ? `${styles.toggle} ${className}` : styles.toggle}
      onClick={() => onChange(!checked)}
    />
  )
}
