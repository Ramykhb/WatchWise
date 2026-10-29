import chevLeftIcon from '../../assets/admin/icon-chev-left.svg'
import chevRightIcon from '../../assets/admin/icon-chev-right.svg'
import type { AdminPageMeta } from '../../lib/adminApi.ts'
import styles from './AdminPagination.module.css'

const fmt = (n: number) => n.toLocaleString('en-US')

/** Page buttons around the current page, e.g. 1 2 3 … 6,888. */
function pageItems(page: number, total: number): (number | '…')[] {
  if (total <= 5) return Array.from({ length: total }, (_, index) => index + 1)
  if (page <= 3) return [1, 2, 3, '…', total]
  if (page >= total - 2) return [1, '…', total - 2, total - 1, total]
  return [1, '…', page, '…', total]
}

/** Pager under an admin table: "Showing 1–20 of 48,210" and the page buttons. */
export default function AdminPagination({ meta, shown, onPage }: { meta: AdminPageMeta; shown: number; onPage: (page: number) => void }) {
  const first = shown === 0 ? 0 : (meta.current_page - 1) * meta.per_page + 1
  return (
    <div className={styles.pagination}>
      <p className={styles.showing}>
        Showing {fmt(first)}–{fmt(first + Math.max(shown - 1, 0))} of {fmt(meta.total)}
      </p>
      {meta.last_page > 1 && (
        <nav className={styles.pages} aria-label="Pages">
          <button
            type="button"
            className={styles.pageButton}
            aria-label="Previous page"
            disabled={meta.current_page === 1}
            onClick={() => onPage(meta.current_page - 1)}
          >
            <img src={chevLeftIcon} width={15} height={15} alt="" />
          </button>
          {pageItems(meta.current_page, meta.last_page).map((item, index) =>
            item === '…' ? (
              <span key={`gap-${index}`} className={styles.pageGap}>
                …
              </span>
            ) : (
              <button
                key={item}
                type="button"
                className={item === meta.current_page ? styles.pageOn : styles.pageButton}
                aria-current={item === meta.current_page ? 'page' : undefined}
                onClick={() => onPage(item)}
              >
                {fmt(item)}
              </button>
            ),
          )}
          <button
            type="button"
            className={styles.pageButton}
            aria-label="Next page"
            disabled={meta.current_page === meta.last_page}
            onClick={() => onPage(meta.current_page + 1)}
          >
            <img src={chevRightIcon} width={15} height={15} alt="" />
          </button>
        </nav>
      )}
    </div>
  )
}
