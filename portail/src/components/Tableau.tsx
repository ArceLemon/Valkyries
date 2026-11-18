import type { ReactNode } from 'react'
import { cx } from '../lib/cx'

/**
 * Tableau sémantique. Sur petit écran, on masque les colonnes secondaires
 * (classe « hidden md:table-cell » sur Th et Td) plutôt que de faire défiler.
 */
export function Tableau({ legende, children }: { legende: string; children: ReactNode }) {
  return (
    <div className="overflow-x-auto rounded-panel border border-border bg-surface">
      <table className="w-full border-collapse text-left text-sm">
        <caption className="sr-only">{legende}</caption>
        {children}
      </table>
    </div>
  )
}

export function Th({ children, className }: { children?: ReactNode; className?: string }) {
  return (
    <th
      scope="col"
      className={cx('bg-surface-2 px-4 py-3 font-medium whitespace-nowrap text-muted', className)}
    >
      {children}
    </th>
  )
}

export function Td({ children, className }: { children?: ReactNode; className?: string }) {
  return <td className={cx('border-t border-border px-4 py-3 align-top', className)}>{children}</td>
}
